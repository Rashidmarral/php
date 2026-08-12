<?php

namespace App\Support\Zatca;

/**
 * Generates a ZATCA-shaped UBL 2.1 simplified tax invoice XML for a single
 * invoice. Field structure follows ZATCA's documented XML implementation
 * standard (invoice type code 388, simplified subtype via
 * InvoiceTypeCode/@name "0200000"), including the PIH (previous invoice
 * hash) chain element ZATCA requires for Phase 2 integrity checking.
 *
 * Note: this produces the invoice *document*; it does not apply the
 * cryptographic stamp (digital signature over the canonicalized XML using
 * the certificate issued during CSID onboarding) — that step depends on
 * having a real ZATCA-issued certificate from Company::zatca_compliance_csid
 * / zatca_production_csid, which only exists after a company completes
 * onboarding with a real OTP from their own Fatoora account.
 */
class UblInvoice
{
    private const NS_CAC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2';
    private const NS_CBC = 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2';

    public static function build(array $invoice, array $company, ?array $client, array $items, string $uuid, int $icv, string $previousHash): string
    {
        $issueDateTime = strtotime((string) $invoice['created_at']) ?: time();
        $issueDate = date('Y-m-d', $issueDateTime);
        $issueTime = date('H:i:s', $issueDateTime);
        $vatAmount = (float) ($invoice['vat_amount'] ?? 0);
        $total = (float) $invoice['total'];
        $subtotal = $total - $vatAmount;

        $xml = new \SimpleXMLElement(
            '<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2" '
            . 'xmlns:cac="' . self::NS_CAC . '" xmlns:cbc="' . self::NS_CBC . '" '
            . 'xmlns:ext="urn:oasis:names:specification:ubl:schema:xsd:CommonExtensionComponents-2"/>'
        );

        self::cbc($xml, 'ProfileID', 'reporting:1.0');
        self::cbc($xml, 'ID', (string) $invoice['invoice_number']);
        self::cbc($xml, 'UUID', $uuid);
        self::cbc($xml, 'IssueDate', $issueDate);
        self::cbc($xml, 'IssueTime', $issueTime);

        $typeCode = self::cbc($xml, 'InvoiceTypeCode', '388');
        $typeCode->addAttribute('name', '0200000'); // simplified tax invoice

        self::cbc($xml, 'DocumentCurrencyCode', 'SAR');
        self::cbc($xml, 'TaxCurrencyCode', 'SAR');
        self::cbc($xml, 'ICV', (string) $icv);

        // PIH — previous invoice hash, chaining this invoice to the last one submitted.
        $pih = self::cac($xml, 'AdditionalDocumentReference');
        self::cbc($pih, 'ID', 'PIH');
        $attachment = self::cac($pih, 'Attachment');
        self::cbc($attachment, 'EmbeddedDocumentBinaryObject', base64_encode($previousHash))
            ->addAttribute('mimeCode', 'text/plain');

        $supplier = self::cac($xml, 'AccountingSupplierParty');
        $supplierParty = self::cac($supplier, 'Party');

        $supplierPostal = self::cac($supplierParty, 'PostalAddress');
        self::cbc($supplierPostal, 'StreetName', (string) ($company['street_name'] ?? ''));
        self::cbc($supplierPostal, 'BuildingNumber', (string) ($company['building_number'] ?? ''));
        self::cbc($supplierPostal, 'CitySubdivisionName', (string) ($company['district'] ?? ''));
        self::cbc($supplierPostal, 'CityName', (string) ($company['city'] ?? ''));
        self::cbc($supplierPostal, 'PostalZone', (string) ($company['postal_code'] ?? ''));
        self::cbc(self::cac($supplierPostal, 'Country'), 'IdentificationCode', (string) ($company['country_code'] ?? 'SA'));

        $supplierTaxScheme = self::cac($supplierParty, 'PartyTaxScheme');
        self::cbc($supplierTaxScheme, 'CompanyID', (string) ($company['vat_number'] ?? ''));
        self::cbc(self::cac($supplierTaxScheme, 'TaxScheme'), 'ID', 'VAT');

        self::cbc(self::cac($supplierParty, 'PartyLegalEntity'), 'RegistrationName', (string) ($company['name'] ?? ''));

        if ($client) {
            $customer = self::cac($xml, 'AccountingCustomerParty');
            $customerParty = self::cac($customer, 'Party');
            self::cbc(self::cac($customerParty, 'PartyLegalEntity'), 'RegistrationName', (string) ($client['name'] ?? ''));
        }

        $taxTotal = self::cac($xml, 'TaxTotal');
        self::cbcCurrency($taxTotal, 'TaxAmount', number_format($vatAmount, 2, '.', ''));

        $legalTotal = self::cac($xml, 'LegalMonetaryTotal');
        self::cbcCurrency($legalTotal, 'LineExtensionAmount', number_format($subtotal, 2, '.', ''));
        self::cbcCurrency($legalTotal, 'TaxExclusiveAmount', number_format($subtotal, 2, '.', ''));
        self::cbcCurrency($legalTotal, 'TaxInclusiveAmount', number_format($total, 2, '.', ''));
        self::cbcCurrency($legalTotal, 'PayableAmount', number_format($total, 2, '.', ''));

        foreach ($items as $index => $item) {
            $line = self::cac($xml, 'InvoiceLine');
            self::cbc($line, 'ID', (string) ($index + 1));
            self::cbc($line, 'InvoicedQuantity', (string) $item['qty'])->addAttribute('unitCode', 'EA');
            self::cbcCurrency($line, 'LineExtensionAmount', number_format((float) $item['total'], 2, '.', ''));
            $itemEl = self::cac($line, 'Item');
            self::cbc($itemEl, 'Name', (string) $item['description']);
            $price = self::cac($line, 'Price');
            self::cbcCurrency($price, 'PriceAmount', number_format((float) $item['unit_price'], 2, '.', ''));
        }

        return $xml->asXML();
    }

    /** SHA-256 hash of the canonical XML — this is the value chained into the next invoice's PIH. */
    public static function hash(string $xml): string
    {
        return base64_encode(hash('sha256', $xml, true));
    }

    private static function cbc(\SimpleXMLElement $parent, string $name, string $value): \SimpleXMLElement
    {
        return $parent->addChild("cbc:{$name}", htmlspecialchars($value), self::NS_CBC);
    }

    private static function cac(\SimpleXMLElement $parent, string $name): \SimpleXMLElement
    {
        return $parent->addChild("cac:{$name}", null, self::NS_CAC);
    }

    private static function cbcCurrency(\SimpleXMLElement $parent, string $name, string $value): void
    {
        self::cbc($parent, $name, $value)->addAttribute('currencyID', 'SAR');
    }
}
