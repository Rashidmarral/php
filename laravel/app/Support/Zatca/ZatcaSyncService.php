<?php

namespace App\Support\Zatca;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates one invoice's trip through ZATCA Phase 2: build the UBL XML,
 * hash it (chained to the company's previous invoice hash), sign it
 * (XAdES + QR embedding) once the company is fully onboarded, submit it,
 * and record the outcome back onto the invoice/company.
 *
 * Adapted from Daftri's App\Services\Zatca\ZatcaSyncService. Two scope
 * reductions from the original, both driven by what BuildXact's schema
 * actually has (see ZatcaXmlGenerator's class docblock for the same
 * reasoning):
 *
 *  - No CreditNote/DebitNote support (no such models here) — only
 *    submitInvoice()/buildSignedPayload() were ported, not
 *    submitCreditNote()/submitDebitNote().
 *  - No dedicated zatca_invoice_logs table — BuildXact already tracks
 *    hash-chain/submission state directly on the invoice/company rows
 *    (zatca_uuid/zatca_icv/zatca_hash/zatca_previous_hash/zatca_status/
 *    zatca_submitted_at/zatca_response, zatca_last_icv/
 *    zatca_last_invoice_hash), so this writes there instead of a log
 *    table, preserving BuildXact's existing storage shape rather than
 *    introducing Daftri's.
 *  - BuildXact always submits as a simplified (B2C) invoice — see
 *    ZatcaXmlGenerator's docblock on why (Client has no VAT/CR data to
 *    populate a standard/B2B buyer party) — so this only ever calls
 *    ZatcaApiClient::reportInvoice(), never clearInvoice(). The clearance
 *    method is still fully ported on ZatcaApiClient for when BuildXact's
 *    Client model grows the fields a real B2B buyer party needs.
 */
class ZatcaSyncService
{
    /** ZATCA's documented invoice-type-code "name" for a simplified (B2C) invoice. */
    public const SIMPLIFIED_PROFILE = '0200000';

    /** ZATCA's documented invoice-type-code "name" for a standard (B2B) invoice. */
    public const STANDARD_PROFILE = '0100000';

    public function __construct(
        private readonly ZatcaCryptoService $crypto,
        private readonly ZatcaXmlGenerator $xml,
        private readonly ZatcaApiClient $api,
        private readonly ZatcaCertificateService $certificates,
        private readonly ZatcaXadesSigner $signer,
    ) {}

    /**
     * Builds the plain (unsigned) invoice XML and its XAdES content hash —
     * the same hash that becomes this invoice's zatca_hash / the next
     * invoice's zatca_previous_hash, whether or not the company is
     * actually onboarded yet (chaining is eager — see class docblock on
     * InvoiceController::chainZatca() for why BuildXact advances the
     * chain at invoice creation rather than only at submission time).
     *
     * @return array{0: string, 1: string} [unsignedXml, invoiceHashBase64]
     */
    public function buildUnsignedXml(Invoice $invoice, Company $company, ?Client $client, array $items, string $uuid, int $icv, string $previousHash): array
    {
        $unsignedXml = $this->xml->generate($invoice, $company, $client, $items, self::SIMPLIFIED_PROFILE, $previousHash, $uuid, $icv);
        $hash = $this->signer->contentHash($unsignedXml);

        return [$unsignedXml, $hash];
    }

    public function genesisHash(): string
    {
        return $this->crypto->genesisHash();
    }

    /**
     * Builds the fully signed XML (XAdES UBLExtensions + cac:Signature +
     * embedded QR) that actually gets submitted to ZATCA, and the Phase 2
     * QR TLV payload (base64 text — render it with QrGenerator::
     * renderSvgDataUri() for display) — both built from the company's own
     * key material and CSID certificate. QR tag 9 ("certificate
     * signature") is a property of the certificate itself, not of any
     * individual invoice, so nothing here depends on the submission
     * having happened yet or having succeeded.
     *
     * @return array{0: string, 1: ?string} [signedXml, qrTlvBase64]
     */
    public function buildSignedPayload(Company $company, string $unsignedXml, string $invoiceHash, string $certificateBase64, \DateTimeInterface $issueDate, float $total, float $vatTotal): array
    {
        $signature = $this->crypto->signInvoiceHash($company, $invoiceHash);

        if (! $signature) {
            Log::error('ZATCA: could not sign invoice hash — company has no private key on file', ['company_id' => $company->id]);

            return [$unsignedXml, null];
        }

        $publicKey = $this->certificates->publicKeyBytes($certificateBase64);
        $certificateSignature = $this->certificates->certificateSignature($certificateBase64);

        $qrTlv = QrGenerator::buildTlvPayloadPhase2(
            $company->name,
            (string) $company->vat_number,
            $issueDate,
            $total,
            $vatTotal,
            $invoiceHash,
            $signature,
            $publicKey,
            $certificateSignature,
        );

        if (! $qrTlv) {
            return [$unsignedXml, null];
        }

        $signedXml = $this->signer->sign($company, $unsignedXml, $invoiceHash, $certificateBase64, $qrTlv, $signature);

        return [$signedXml, $qrTlv];
    }

    /**
     * Submits an invoice's already-computed hash-chain data (zatca_uuid/
     * zatca_icv/zatca_previous_hash, set by InvoiceController::
     * chainZatca() at creation) to ZATCA's reporting endpoint, and writes
     * the outcome back onto the invoice (and, on success, the company's
     * zatca_last_invoice_hash).
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function submitInvoice(Invoice $invoice, Company $company, ?Client $client, array $items): array
    {
        if (! $company->isZatcaOnboarded()) {
            return ['ok' => false, 'status' => 'failed', 'message' => __('ZATCA Phase 2 onboarding is not complete for this company yet.')];
        }
        if (empty($invoice->zatca_uuid) || empty($invoice->zatca_hash)) {
            return ['ok' => false, 'status' => 'failed', 'message' => __('This invoice has no ZATCA chain data.')];
        }

        [$unsignedXml] = $this->buildUnsignedXml(
            $invoice, $company, $client, $items,
            (string) $invoice->zatca_uuid, (int) $invoice->zatca_icv, (string) $invoice->zatca_previous_hash,
        );

        $csid = $company->zatcaCsidFor();
        $secret = $company->zatcaSecretFor();

        [$xmlToSubmit] = $this->buildSignedPayload(
            $company, $unsignedXml, (string) $invoice->zatca_hash, (string) $csid,
            $invoice->created_at ?? now(), (float) $invoice->total, (float) $invoice->vat_amount,
        );

        $xmlBase64 = base64_encode($xmlToSubmit);

        try {
            $response = $this->api->reportInvoice(
                $company->zatca_environment, (string) $csid, (string) $secret,
                $xmlBase64, (string) $invoice->zatca_hash, (string) $invoice->zatca_uuid,
            );
        } catch (\Throwable $e) {
            $invoice->update(['zatca_status' => 'failed', 'zatca_response' => Str::limit($e->getMessage(), 2000)]);

            return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
        }

        if ($response->successful()) {
            $invoice->update([
                'zatca_status' => 'reported',
                'zatca_submitted_at' => now(),
                'zatca_response' => $response->body(),
            ]);
            $company->update(['zatca_last_invoice_hash' => $invoice->zatca_hash, 'zatca_last_sync_at' => now()]);

            return ['ok' => true, 'status' => 'reported', 'message' => __('Invoice reported to ZATCA.')];
        }

        $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 2000);
        $invoice->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);

        return ['ok' => false, 'status' => 'failed', 'message' => $errorMessage];
    }
}
