<?php

namespace App\Support\Zatca;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use DOMDocument;

/**
 * Builds the UBL 2.1 XML representation of an invoice per ZATCA's
 * e-invoicing implementation standard: standard tax invoices (B2B) use
 * InvoiceTypeCode name "0100000" (cleared before delivery to the buyer),
 * simplified invoices (B2C) use "0200000" (reported after delivery).
 *
 * Ported from Daftri's App\Services\Zatca\ZatcaXmlGenerator. Every
 * business-rule comment (element order, dual TaxTotal, PartyIdentification
 * scheme, etc.) documents a real ZATCA validator rejection Daftri's
 * implementation was debugged against and is preserved verbatim; only the
 * data plumbing was adapted to BuildXact's own Invoice/Company/Client/
 * InvoiceItem schema, which differs from Daftri's in two remaining
 * structural ways documented inline:
 *
 *  1. BuildXact's InvoiceItem carries no per-line VAT rate/amount or tax
 *     category — only Invoice::vat_rate/vat_amount are known, so each
 *     line's VAT is derived proportionally from that single header rate
 *     rather than read from a per-line TaxRate relation.
 *  2. generateForCreditNote()/generateForDebitNote() below ARE now
 *     ported (BuildXact's CreditNote/DebitNote models are the same
 *     simple, single-header-VAT-rate shape as Invoice/InvoiceItem — see
 *     those models' docblocks), adapted from Daftri's equivalent methods
 *     the same way generate() above already is.
 *
 * BuildXact's Client originally had no vat_number/CR/address-breakdown
 * fields at all — every real invoice was forced through the simplified/
 * B2C profile regardless of who the buyer actually was, because there was
 * no data to populate a standard/B2B buyer party with. A migration
 * (2026_09_11_000001_add_zatca_b2b_fields_to_clients_table) gave Client
 * the same vat_number/cr_number/structured-address columns Company
 * already had, and isB2bEligible() below is now the single choke point
 * (also used by ZatcaSyncService) that decides, per invoice, whether
 * enough of that data exists to emit a real cac:AccountingCustomerParty
 * and submit as standard/B2B instead of simplified/B2C. A client with
 * neither field set still falls back to exactly the old simplified
 * buyer-party shape (name + free-text address line only) — no regression
 * for existing clients.
 */
class ZatcaXmlGenerator
{
    /**
     * ZATCA's Saudi VAT registration number format: exactly 15 digits,
     * the first and last of which are always '3' (the fixed group
     * prefix/suffix ZATCA assigns every KSA VAT number). No existing
     * regex/validation constant for this was found anywhere else in the
     * codebase to reuse — Company::vat_number has never had format
     * validation (SettingsController/CompanyController just trim() and
     * save it) — so this is a new, single-source-of-truth constant used
     * both by isB2bEligible() below and by ClientController's own input
     * validation, so the two can't drift apart.
     */
    public const VAT_NUMBER_PATTERN = '/^3\d{13}3$/';

    /**
     * Minimum bar for treating a client as a genuine standard/B2B
     * counterparty rather than a walk-in/simplified buyer: a
     * ZATCA-format-valid VAT registration number AND a CR number, since
     * those are the two identifiers ZatcaXmlGenerator's party() helper
     * actually renders into the buyer's PartyIdentification (schemeID
     * CRN) and PartyTaxScheme/CompanyID elements — the fields ZATCA's
     * validator consults to confirm the buyer is a real registered
     * taxable person. A structured address is deliberately NOT required:
     * party()'s $hasAddress check already treats PostalAddress as
     * optional (every buyer field below the party name is optional per
     * UBL's PartyType), and a real ZATCA-cleared invoice with a
     * VAT+CR-only buyer and no address on file is still schema-valid — so
     * requiring one here would just push otherwise-eligible clients back
     * onto the simplified path for no compliance benefit. Address fields
     * ARE still populated into the buyer party whenever they happen to be
     * on file (see generate()).
     */
    public function isB2bEligible(?Client $client): bool
    {
        if (! $client) {
            return false;
        }

        $vatNumber = trim((string) ($client->vat_number ?? ''));
        $crNumber = trim((string) ($client->cr_number ?? ''));

        return $crNumber !== '' && preg_match(self::VAT_NUMBER_PATTERN, $vatNumber) === 1;
    }

    /**
     * @param  array<int, array{description?: string, qty: float|string, unit_price: float|string, total?: float|string}>  $items
     *                                                                                                                     Plain associative arrays (matching what
     *                                                                                                                     InvoiceController already builds/reads for the
     *                                                                                                                     PDF/QR paths), not Eloquent models or stdClass.
     */
    public function generate(Invoice $invoice, Company $company, ?Client $client, array $items, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv = 1): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $issuedAt = $invoice->created_at ?? now();

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', (string) $invoice->invoice_number);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $issuedAt->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', '388');
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        if ($this->isB2bEligible($client)) {
            // Standard/B2B buyer party — see isB2bEligible()'s docblock for
            // exactly why VAT+CR (not address) is the eligibility bar.
            // Element order/scheme mirror the seller party above verbatim
            // (party() renders both the same way).
            $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
                'name' => $client->name,
                'vat_number' => $client->vat_number,
                'id_scheme' => 'CRN',
                'id_value' => $client->cr_number,
                'street' => $client->street_name ?: $client->address,
                'building_number' => $client->building_number,
                'district' => $client->district,
                'city' => $client->city,
                'postal_code' => $client->postal_code,
            ]));
        } else {
            // No vat_number/cr_number on file for this client (or it
            // fails ZATCA's VAT format) — only name and a single
            // free-text address line are available, so this stays exactly
            // the pre-existing simplified/B2C buyer-party shape.
            $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
                'name' => $client->name ?? __('Walk-in customer'),
                'vat_number' => null,
                'id_scheme' => null,
                'id_value' => null,
                'street' => $client->address ?? null,
                'building_number' => null,
                'district' => null,
                'city' => null,
                'postal_code' => null,
            ]));
        }

        // KSA-5 "supply date" — ZATCA requires standard tax invoices to
        // state the actual delivery/supply date (BR-KSA-15).
        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $issuedAt->format('Y-m-d'));
        $root->appendChild($delivery);

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        $root->appendChild($paymentMeans);

        // Always present (even at 0.00, since BuildXact invoices don't
        // model a header-level discount) — confirmed against a real
        // ZATCA-cleared invoice, which carries this element
        // unconditionally rather than only when a discount applies.
        $root->appendChild($this->documentAllowanceCharge($doc, 0.0, (float) $invoice->vat_rate));

        $vatAmount = (float) $invoice->vat_amount;
        $vatRate = (float) $invoice->vat_rate;

        $this->appendDualTaxTotal($doc, $root, $vatAmount, $items, $vatRate);

        $subtotal = (float) $invoice->total - $vatAmount;

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', $subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', $subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', (float) $invoice->total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:AllowanceTotalAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PrepaidAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', (float) $invoice->total);
        $root->appendChild($monetaryTotal);

        foreach ($items as $index => $item) {
            $root->appendChild($this->invoiceLine($doc, $index, $item, $vatRate));
        }

        return $doc->saveXML();
    }

    /**
     * Credit notes use the same UBL Invoice-2 schema as tax invoices —
     * differentiated only by InvoiceTypeCode 381 and by carrying a
     * cac:BillingReference back to the invoice they correct, plus a
     * KSA-10 "reason" (cbc:InstructionNote nested under cac:PaymentMeans,
     * a sibling of PaymentMeansCode per UBL's PaymentMeansType — not a
     * top-level document child). They are never issued standalone: they
     * must continue the exact same company-wide ICV/PIH chain as regular
     * invoices (see ZatcaSyncService::submitCreditNote()).
     *
     * @param  array<int, array{description?: string, qty: float|string, unit_price: float|string, total?: float|string}>  $items
     */
    public function generateForCreditNote(CreditNote $creditNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv = 1): string
    {
        return $this->generateNote($creditNote, $company, $client, $items, $originalInvoice, '381', $creditNote->reason ?: 'Sales return / correction', $invoiceTypeName, $previousInvoiceHash, $uuid, $icv);
    }

    /**
     * Debit notes use the same UBL Invoice-2 schema as credit notes — only
     * InvoiceTypeCode differs (383 instead of 381). They raise what the
     * customer owes rather than reducing it, but still carry the same
     * mandatory BillingReference back to the original invoice (BR-KSA-56)
     * and continue the same company-wide ICV/PIH chain.
     *
     * @param  array<int, array{description?: string, qty: float|string, unit_price: float|string, total?: float|string}>  $items
     */
    public function generateForDebitNote(DebitNote $debitNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv = 1): string
    {
        return $this->generateNote($debitNote, $company, $client, $items, $originalInvoice, '383', $debitNote->reason ?: 'Additional charge / correction', $invoiceTypeName, $previousInvoiceHash, $uuid, $icv);
    }

    /**
     * Shared body for generateForCreditNote()/generateForDebitNote() —
     * both models carry the identical note_number/issue_date/vat_rate/
     * vat_amount/total shape (see their class docblocks), so only the
     * InvoiceTypeCode and default reason text actually differ between
     * them. Structure otherwise mirrors generate() above exactly (same
     * party()/appendDualTaxTotal()/invoiceLine()/documentAllowanceCharge()
     * helpers), with a cac:BillingReference inserted before the ICV/PIH
     * AdditionalDocumentReference — UBL's InvoiceType element sequence
     * places BillingReference first, so it must be emitted before those,
     * not after (a real ZATCA validator rejection Daftri's implementation
     * was debugged against — see its own docblock history).
     *
     * @param  array<int, array{description?: string, qty: float|string, unit_price: float|string, total?: float|string}>  $items
     */
    private function generateNote(CreditNote|DebitNote $note, Company $company, ?Client $client, array $items, Invoice $originalInvoice, string $typeCodeValue, string $reason, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $issuedAt = $note->created_at ?? now();

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', (string) $note->note_number);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $issuedAt->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', $typeCodeValue);
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        $billingReference = $doc->createElement('cac:BillingReference');
        $docRef = $doc->createElement('cac:InvoiceDocumentReference');
        $this->append($doc, $docRef, 'cbc:ID', (string) $originalInvoice->invoice_number);
        if (! empty($originalInvoice->zatca_uuid)) {
            $this->append($doc, $docRef, 'cbc:UUID', (string) $originalInvoice->zatca_uuid);
        }
        $billingReference->appendChild($docRef);
        $root->appendChild($billingReference);

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        if ($this->isB2bEligible($client)) {
            $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
                'name' => $client->name,
                'vat_number' => $client->vat_number,
                'id_scheme' => 'CRN',
                'id_value' => $client->cr_number,
                'street' => $client->street_name ?: $client->address,
                'building_number' => $client->building_number,
                'district' => $client->district,
                'city' => $client->city,
                'postal_code' => $client->postal_code,
            ]));
        } else {
            $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
                'name' => $client->name ?? __('Walk-in customer'),
                'vat_number' => null,
                'id_scheme' => null,
                'id_value' => null,
                'street' => $client->address ?? null,
                'building_number' => null,
                'district' => null,
                'city' => null,
                'postal_code' => null,
            ]));
        }

        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $issuedAt->format('Y-m-d'));
        $root->appendChild($delivery);

        // KSA-10 "Reason for issuing a Credit/Debit Note" (BR-KSA-17).
        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        $this->append($doc, $paymentMeans, 'cbc:InstructionNote', $reason);
        $root->appendChild($paymentMeans);

        $vatAmount = (float) $note->vat_amount;
        $vatRate = (float) $note->vat_rate;

        $root->appendChild($this->documentAllowanceCharge($doc, 0.0, $vatRate));

        $this->appendDualTaxTotal($doc, $root, $vatAmount, $items, $vatRate);

        $subtotal = (float) $note->total - $vatAmount;

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', $subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', $subtotal);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', (float) $note->total);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:AllowanceTotalAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PrepaidAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', (float) $note->total);
        $root->appendChild($monetaryTotal);

        foreach ($items as $index => $item) {
            $root->appendChild($this->invoiceLine($doc, $index, $item, $vatRate));
        }

        return $doc->saveXML();
    }

    /**
     * Before issuing a production CSID, ZATCA requires proof the EGS can
     * generate the document type/profile combinations it declared support
     * for in its CSR — tax invoice (388), in the standard/B2B (0100000)
     * and/or simplified/B2C (0200000) profile, matching the company's own
     * zatca_sync_b2b/zatca_sync_b2c settings. Credit/debit note profiles
     * are not included here (see class docblock — no such model in
     * BuildXact yet), so a company should only declare the invoice-type
     * flag's Standard/Simplified bits, never expect a full 6-combination
     * compliance run.
     *
     * $issuedAt must be the exact same instant the caller later passes to
     * ZatcaSyncService::buildSignedPayload() as its $issueDate — ZATCA
     * checks the QR code's own timestamp (KSA-25) against this document's
     * IssueDate/IssueTime, and a caller using separate now() calls for
     * each risks drift (canonicalization + signing both take real time)
     * that fails that check.
     */
    public function generateComplianceSample(Company $company, string $invoiceTypeName, ?string $previousInvoiceHash, string $uuid, int $icv, \DateTimeInterface $issuedAt): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = false;

        $root = $doc->createElementNS('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $doc->appendChild($root);

        $this->append($doc, $root, 'cbc:ProfileID', 'reporting:1.0');
        $this->append($doc, $root, 'cbc:ID', 'COMPLIANCE-'.$icv);
        $this->append($doc, $root, 'cbc:UUID', $uuid);
        $this->append($doc, $root, 'cbc:IssueDate', $issuedAt->format('Y-m-d'));
        $this->append($doc, $root, 'cbc:IssueTime', $issuedAt->format('H:i:s'));

        $typeCode = $this->append($doc, $root, 'cbc:InvoiceTypeCode', '388');
        $typeCode->setAttribute('name', $invoiceTypeName);

        $this->append($doc, $root, 'cbc:DocumentCurrencyCode', 'SAR');
        $this->append($doc, $root, 'cbc:TaxCurrencyCode', 'SAR');

        $root->appendChild($this->icvReference($doc, $icv));

        if ($previousInvoiceHash) {
            $root->appendChild($this->pihReference($doc, $previousInvoiceHash));
        }

        $root->appendChild($this->party($doc, 'cac:AccountingSupplierParty', [
            'name' => $company->name,
            'vat_number' => $company->vat_number,
            'id_scheme' => 'CRN',
            'id_value' => $company->cr_number,
            'street' => $company->street_name ?: $company->address,
            'building_number' => $company->building_number,
            'district' => $company->district,
            'city' => $company->city,
            'postal_code' => $company->postal_code,
        ]));

        $root->appendChild($this->party($doc, 'cac:AccountingCustomerParty', [
            'name' => 'ZATCA Compliance Test Buyer',
            'vat_number' => '300000000000003',
            'id_scheme' => 'CRN',
            'id_value' => '1000000000',
            'street' => 'King Fahd Road',
            'building_number' => '1111',
            'district' => 'Al Olaya',
            'city' => 'Riyadh',
            'postal_code' => '12222',
        ]));

        $delivery = $doc->createElement('cac:Delivery');
        $this->append($doc, $delivery, 'cbc:ActualDeliveryDate', $issuedAt->format('Y-m-d'));
        $root->appendChild($delivery);

        $paymentMeans = $doc->createElement('cac:PaymentMeans');
        $this->append($doc, $paymentMeans, 'cbc:PaymentMeansCode', '1');
        $root->appendChild($paymentMeans);

        $root->appendChild($this->documentAllowanceCharge($doc, 0.0, 15.0));

        $sampleItem = ['qty' => 2.00, 'unit_price' => 2.00, 'total' => 4.00];
        $this->appendDualTaxTotal($doc, $root, 0.60, [$sampleItem], 15.0);

        $monetaryTotal = $doc->createElement('cac:LegalMonetaryTotal');
        $this->appendAmount($doc, $monetaryTotal, 'cbc:LineExtensionAmount', 4.00);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxExclusiveAmount', 4.00);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:TaxInclusiveAmount', 4.60);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:AllowanceTotalAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PrepaidAmount', 0.0);
        $this->appendAmount($doc, $monetaryTotal, 'cbc:PayableAmount', 4.60);
        $root->appendChild($monetaryTotal);

        $root->appendChild($this->invoiceLine($doc, 0, $sampleItem, 15.0, 'Compliance Test Item'));

        return $doc->saveXML();
    }

    public function newUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        $hex = bin2hex($data);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    private function party(DOMDocument $doc, string $wrapperTag, array $data): \DOMElement
    {
        $wrapper = $doc->createElement($wrapperTag);
        $party = $doc->createElement('cac:Party');

        // UBL PartyType element order matters: PartyIdentification, then
        // PostalAddress, then PartyTaxScheme, then PartyLegalEntity — ZATCA's
        // validator (BR-KSA-08) rejects the seller party without a
        // PartyIdentification carrying a recognised scheme (CRN, MOM, MLS,
        // SAG, OTH, 700) and alphanumeric-only ID.
        if (! empty($data['id_value']) && ! empty($data['id_scheme'])) {
            $identification = $doc->createElement('cac:PartyIdentification');
            $id = $this->append($doc, $identification, 'cbc:ID', preg_replace('/[^A-Za-z0-9]/', '', (string) $data['id_value']));
            $id->setAttribute('schemeID', $data['id_scheme']);
            $party->appendChild($identification);
        }

        $hasAddress = ! empty($data['street']) || ! empty($data['city']) || ! empty($data['building_number']) || ! empty($data['district']) || ! empty($data['postal_code']);

        if ($hasAddress) {
            // UBL's PostalAddressType requires StreetName before
            // BuildingName/BuildingNumber — ZATCA's XSD validation rejects
            // the reverse order outright (and, since a schema violation
            // aborts deeper validation, was also the real cause behind
            // otherwise-inexplicable "missing ICV"/"missing seller VAT"
            // business-rule errors reported alongside it).
            $address = $doc->createElement('cac:PostalAddress');
            if (! empty($data['street'])) {
                $this->append($doc, $address, 'cbc:StreetName', $data['street']);
            }
            if (! empty($data['building_number'])) {
                $this->append($doc, $address, 'cbc:BuildingNumber', $data['building_number']);
            }
            if (! empty($data['district'])) {
                $this->append($doc, $address, 'cbc:CitySubdivisionName', $data['district']);
            }
            if (! empty($data['city'])) {
                $this->append($doc, $address, 'cbc:CityName', $data['city']);
            }
            if (! empty($data['postal_code'])) {
                $this->append($doc, $address, 'cbc:PostalZone', $data['postal_code']);
            }
            $country = $doc->createElement('cac:Country');
            $this->append($doc, $country, 'cbc:IdentificationCode', 'SA');
            $address->appendChild($country);
            $party->appendChild($address);
        }

        if (! empty($data['vat_number'])) {
            // UBL's PartyTaxSchemeType ends its sequence with a mandatory
            // (not optional) cac:TaxScheme — omitting it is itself an XSD
            // violation, and since it sits inside the same
            // AccountingSupplierParty subtree as the seller's CompanyID,
            // this was also the real cause of the "missing seller VAT
            // number" (BR-KSA-39) error reported alongside it.
            $taxScheme = $doc->createElement('cac:PartyTaxScheme');
            $this->append($doc, $taxScheme, 'cbc:CompanyID', str_pad(preg_replace('/[^0-9]/', '', (string) $data['vat_number']), 15, '0', STR_PAD_LEFT));
            $scheme = $doc->createElement('cac:TaxScheme');
            $this->append($doc, $scheme, 'cbc:ID', 'VAT');
            $taxScheme->appendChild($scheme);
            $party->appendChild($taxScheme);
        }

        $legalEntity = $doc->createElement('cac:PartyLegalEntity');
        $this->append($doc, $legalEntity, 'cbc:RegistrationName', $data['name'] ?: '');
        $party->appendChild($legalEntity);

        $wrapper->appendChild($party);

        return $wrapper;
    }

    /**
     * ZATCA's dual-currency EN16931 profile requires exactly two document
     * level cac:TaxTotal (BG-22) elements even though DocumentCurrencyCode
     * and TaxCurrencyCode are both always SAR here: one bare (TaxAmount
     * only — BR-KSA-EN16931-09) and one carrying the BG-23 VAT breakdown
     * as cac:TaxSubtotal (BR-KSA-EN16931-08). Confirmed against a working
     * reference ZATCA integration — both rules fire unless both TaxTotal
     * elements exist.
     *
     * BuildXact invoices carry a single header-level VAT rate rather than
     * a per-line TaxRate relation, so — unlike Daftri's original, which
     * groups by (category, rate) across possibly-mixed-rate lines — this
     * always produces exactly one TaxSubtotal group for the invoice's one
     * rate.
     */
    private function appendDualTaxTotal(DOMDocument $doc, \DOMElement $root, float $vatTotal, iterable $items, float $vatRate): void
    {
        $bare = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $bare, 'cbc:TaxAmount', $vatTotal);
        $root->appendChild($bare);

        $withBreakdown = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $withBreakdown, 'cbc:TaxAmount', $vatTotal);

        $taxable = 0.0;
        foreach ($items as $item) {
            $taxable += (float) $item['qty'] * (float) $item['unit_price'];
        }

        $subtotal = $doc->createElement('cac:TaxSubtotal');
        $this->appendAmount($doc, $subtotal, 'cbc:TaxableAmount', $taxable);
        $this->appendAmount($doc, $subtotal, 'cbc:TaxAmount', $vatTotal);

        $category = $doc->createElement('cac:TaxCategory');
        $this->append($doc, $category, 'cbc:ID', $this->taxCategoryCode($vatRate));
        $this->append($doc, $category, 'cbc:Percent', number_format($vatRate, 2, '.', ''));
        $scheme = $doc->createElement('cac:TaxScheme');
        $this->append($doc, $scheme, 'cbc:ID', 'VAT');
        $category->appendChild($scheme);
        $subtotal->appendChild($category);

        $withBreakdown->appendChild($subtotal);
        $root->appendChild($withBreakdown);
    }

    /**
     * @param  array{description?: string, qty: float|string, unit_price: float|string}  $item
     */
    private function invoiceLine(DOMDocument $doc, int $index, array $item, float $vatRate, ?string $descriptionOverride = null): \DOMElement
    {
        $line = $doc->createElement('cac:InvoiceLine');
        $this->append($doc, $line, 'cbc:ID', (string) ($index + 1));
        $qty = $this->append($doc, $line, 'cbc:InvoicedQuantity', number_format((float) $item['qty'], 2, '.', ''));
        $qty->setAttribute('unitCode', 'PCE');

        $lineNet = (float) $item['qty'] * (float) $item['unit_price'];
        $lineVat = round($lineNet * $vatRate / 100, 2);

        $this->appendAmount($doc, $line, 'cbc:LineExtensionAmount', $lineNet);

        // KSA-11 (line VAT amount) and KSA-12 (line amount with VAT,
        // i.e. net + VAT) — BR-KSA-51 requires both on every line.
        $lineTax = $doc->createElement('cac:TaxTotal');
        $this->appendAmount($doc, $lineTax, 'cbc:TaxAmount', $lineVat);
        $this->appendAmount($doc, $lineTax, 'cbc:RoundingAmount', $lineNet + $lineVat);
        $line->appendChild($lineTax);

        $itemEl = $doc->createElement('cac:Item');
        $this->append($doc, $itemEl, 'cbc:Name', $descriptionOverride ?? (string) ($item['description'] ?? ''));
        $taxCategory = $doc->createElement('cac:ClassifiedTaxCategory');
        $this->append($doc, $taxCategory, 'cbc:ID', $this->taxCategoryCode($vatRate));
        $this->append($doc, $taxCategory, 'cbc:Percent', number_format($vatRate, 2, '.', ''));
        $lineTaxScheme = $doc->createElement('cac:TaxScheme');
        $this->append($doc, $lineTaxScheme, 'cbc:ID', 'VAT');
        $taxCategory->appendChild($lineTaxScheme);
        $itemEl->appendChild($taxCategory);
        $line->appendChild($itemEl);

        $price = $doc->createElement('cac:Price');
        $this->appendAmount($doc, $price, 'cbc:PriceAmount', (float) $item['unit_price']);
        $price->appendChild($this->lineAllowanceCharge($doc));
        $line->appendChild($price);

        return $line;
    }

    /**
     * KSA's mandatory Invoice Counter Value — a company-wide, ever
     * incrementing sequence across every document ever submitted,
     * distinct from the invoice's own business-facing number and from the
     * PIH hash chain.
     */
    private function icvReference(DOMDocument $doc, int $icv): \DOMElement
    {
        $ref = $doc->createElement('cac:AdditionalDocumentReference');
        $this->append($doc, $ref, 'cbc:ID', 'ICV');
        $this->append($doc, $ref, 'cbc:UUID', (string) $icv);

        return $ref;
    }

    private function pihReference(DOMDocument $doc, string $previousInvoiceHash): \DOMElement
    {
        $pih = $doc->createElement('cac:AdditionalDocumentReference');
        $this->append($doc, $pih, 'cbc:ID', 'PIH');
        $attachment = $doc->createElement('cac:Attachment');
        $binary = $doc->createElement('cbc:EmbeddedDocumentBinaryObject', $previousInvoiceHash);
        $binary->setAttribute('mimeCode', 'text/plain');
        $attachment->appendChild($binary);
        $pih->appendChild($attachment);

        return $pih;
    }

    /**
     * A single document-level discount, expressed as the UBL
     * cac:AllowanceCharge ZATCA expects to back up LegalMonetaryTotal's
     * AllowanceTotalAmount (BR-CO-11: their sum must match). BuildXact
     * invoices don't model a header-level discount at all, so
     * $discountTotal is always 0.0 for a real invoice — this element is
     * still emitted unconditionally (see call sites) to match a real
     * ZATCA-cleared invoice's structure.
     */
    private function documentAllowanceCharge(DOMDocument $doc, float $discountTotal, float $vatRate): \DOMElement
    {
        $allowance = $doc->createElement('cac:AllowanceCharge');
        $this->append($doc, $allowance, 'cbc:ID', '1');
        $this->append($doc, $allowance, 'cbc:ChargeIndicator', 'false');
        $this->append($doc, $allowance, 'cbc:AllowanceChargeReason', 'discount');
        $this->appendAmount($doc, $allowance, 'cbc:Amount', $discountTotal);

        $category = $doc->createElement('cac:TaxCategory');
        $id = $this->append($doc, $category, 'cbc:ID', $this->taxCategoryCode($vatRate));
        $id->setAttribute('schemeAgencyID', '6');
        $id->setAttribute('schemeID', 'UN/ECE 5305');
        $this->append($doc, $category, 'cbc:Percent', number_format($vatRate, 2, '.', ''));
        $scheme = $doc->createElement('cac:TaxScheme');
        $schemeId = $this->append($doc, $scheme, 'cbc:ID', 'VAT');
        $schemeId->setAttribute('schemeAgencyID', '6');
        $schemeId->setAttribute('schemeID', 'UN/ECE 5153');
        $category->appendChild($scheme);
        $allowance->appendChild($category);

        return $allowance;
    }

    /**
     * The line-level counterpart to documentAllowanceCharge() above,
     * nested under cac:Price per UBL's PriceType — confirmed present
     * (Amount 0.00, no per-line discount tracked here) on a real
     * ZATCA-cleared invoice, so every line carries one unconditionally.
     */
    private function lineAllowanceCharge(DOMDocument $doc): \DOMElement
    {
        $allowance = $doc->createElement('cac:AllowanceCharge');
        $this->append($doc, $allowance, 'cbc:ChargeIndicator', 'false');
        $this->append($doc, $allowance, 'cbc:AllowanceChargeReason', 'Applied Discount');
        $this->appendAmount($doc, $allowance, 'cbc:Amount', 0.0);

        return $allowance;
    }

    /**
     * ZATCA's ClassifiedTaxCategory/ID: S (standard-rated) or Z
     * (zero-rated). BuildXact has no exempt/zero-rated distinction at the
     * data-model level (no TaxRate relation), so — unlike Daftri, which
     * consults a TaxRate::type when one is linked — this always falls
     * back to the same "0% ⇒ zero-rated, otherwise standard" rule Daftri
     * uses for its own pre-TaxRate-era invoices.
     */
    private function taxCategoryCode(float $vatRate): string
    {
        return $vatRate > 0 ? 'S' : 'Z';
    }

    private function append(DOMDocument $doc, \DOMElement $parent, string $tag, string $value): \DOMElement
    {
        $el = $doc->createElement($tag, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        $parent->appendChild($el);

        return $el;
    }

    private function appendAmount(DOMDocument $doc, \DOMElement $parent, string $tag, float $amount): \DOMElement
    {
        $el = $this->append($doc, $parent, $tag, number_format($amount, 2, '.', ''));
        $el->setAttribute('currencyID', 'SAR');

        return $el;
    }
}
