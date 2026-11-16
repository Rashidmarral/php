<?php

namespace App\Support\Zatca;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Orchestrates one invoice's trip through ZATCA Phase 2: build the UBL XML,
 * hash it (chained to the company's previous invoice hash), sign it
 * (XAdES + QR embedding) once the company is fully onboarded, submit it,
 * and record the outcome back onto the invoice/company.
 *
 * Adapted from Daftri's App\Services\Zatca\ZatcaSyncService. One scope
 * reduction from the original, driven by what BuildXact's schema actually
 * has (see ZatcaXmlGenerator's class docblock for the same reasoning):
 *
 *  - No dedicated zatca_invoice_logs table — BuildXact already tracks
 *    hash-chain/submission state directly on the invoice/company rows
 *    (zatca_uuid/zatca_icv/zatca_hash/zatca_previous_hash/zatca_status/
 *    zatca_submitted_at/zatca_response, zatca_last_icv/
 *    zatca_last_invoice_hash), so this writes there instead of a log
 *    table, preserving BuildXact's existing storage shape rather than
 *    introducing Daftri's.
 *
 * BuildXact submits as standard (B2B, ZatcaApiClient::clearInvoice()) or
 * simplified (B2C, ZatcaApiClient::reportInvoice()) per invoice, decided
 * once by ZatcaXmlGenerator::isB2bEligible() and used consistently for
 * both the invoice-type-code passed into buildUnsignedXml() and the
 * submission method chosen in submitInvoice() — see that method's
 * docblock for exactly why VAT+CR is the eligibility bar.
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
        $profile = $this->xml->isB2bEligible($client) ? self::STANDARD_PROFILE : self::SIMPLIFIED_PROFILE;
        $unsignedXml = $this->xml->generate($invoice, $company, $client, $items, $profile, $previousHash, $uuid, $icv);
        $hash = $this->signer->contentHash($unsignedXml);

        return [$unsignedXml, $hash];
    }

    public function genesisHash(): string
    {
        return $this->crypto->genesisHash();
    }

    /**
     * Row-locks the company for the duration of one full submission (the
     * fresh re-check below, through the outbound ZATCA call, through the
     * resulting flat-column/ICV-counter write-back) so that two
     * near-simultaneous submissions for the SAME company — a double-
     * clicked "Submit to ZATCA" button, an automatic queued job racing a
     * manual retry — serialize instead of both reading the same stale
     * company.zatca_last_invoice_hash/zatca_icv_counter and corrupting
     * which submission's outcome that state actually reflects. This is
     * the single choke point every submitInvoice()/submitCreditNote()/
     * submitDebitNote() call goes through, so locking here protects all
     * three uniformly. Ported from Daftri's
     * ZatcaSyncService::lockedSubmission() (security audit finding D-1).
     */
    private function lockedSubmission(int $companyId, \Closure $callback): array
    {
        return DB::transaction(function () use ($companyId, $callback) {
            $company = Company::where('id', $companyId)->lockForUpdate()->first();

            return $callback($company);
        });
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
        if (empty($invoice->zatca_uuid) || empty($invoice->zatca_hash)) {
            return ['ok' => false, 'status' => 'failed', 'message' => __('This invoice has no ZATCA chain data.')];
        }

        return $this->lockedSubmission((int) $company->id, function (Company $company) use ($invoice, $client, $items) {
            if (! $company->isZatcaOnboarded()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('ZATCA Phase 2 onboarding is not complete for this company yet.')];
            }

            // Audit finding ZATCA-03: the caller's own checks above run
            // before this company row is locked — two near-simultaneous
            // submissions for the SAME invoice (a double-click, a queued
            // job racing a manual retry) can both pass them before
            // either's transaction commits. The lock then serializes
            // them, but without re-reading the invoice fresh here, the
            // second one to acquire it would still go on to fire a second
            // live clearance/report call for a document the first one,
            // now committed, already got cleared/reported.
            $invoice = $invoice->fresh();

            if ($invoice->isZatcaLocked()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('This invoice has already been cleared or reported to ZATCA by a concurrent request.')];
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

            // Same eligibility check buildUnsignedXml() used to pick the
            // invoice-type-code above must pick the submission method
            // here — a standard-profile XML submitted via reportInvoice()
            // (or vice versa) is rejected by ZATCA, so both decisions go
            // through the one isB2bEligible() choke point rather than
            // being decided twice.
            $isB2b = $this->xml->isB2bEligible($client);
            $successStatus = $isB2b ? 'cleared' : 'reported';

            $log = ZatcaInvoiceLog::create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'environment' => $company->zatca_environment,
                'invoice_type' => $isB2b ? 'b2b' : 'b2c',
                'direction' => $isB2b ? 'clearance' : 'reporting',
                'status' => 'queued',
                'request_uuid' => $invoice->zatca_uuid,
                'invoice_hash' => $invoice->zatca_hash,
                'previous_invoice_hash' => $invoice->zatca_previous_hash,
                'xml_payload' => $xmlToSubmit,
                'submitted_at' => now(),
            ]);

            try {
                $response = $isB2b
                    ? $this->api->clearInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $invoice->zatca_hash, (string) $invoice->zatca_uuid,
                    )
                    : $this->api->reportInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $invoice->zatca_hash, (string) $invoice->zatca_uuid,
                    );
            } catch (\Throwable $e) {
                $errorMessage = Str::limit($e->getMessage(), 2000);
                $invoice->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
                $log->update(['status' => 'failed', 'error_message' => $errorMessage]);

                return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
            }

            if ($response->successful()) {
                $invoice->update([
                    'zatca_status' => $successStatus,
                    'zatca_submitted_at' => now(),
                    'zatca_response' => $response->body(),
                ]);
                // Advances the confirmed-success PIH cache AND the
                // per-environment ICV counter together, in this one
                // update() call, still under the lock acquired above —
                // see lockedSubmission()'s docblock (audit finding D-1)
                // and the zatca_icv_counter migration's docblock for why
                // this must be a persisted, success-only advance rather
                // than a derived COUNT(*).
                $company->update([
                    'zatca_last_invoice_hash' => $invoice->zatca_hash,
                    'zatca_last_sync_at' => now(),
                    'zatca_icv_counter' => $company->zatca_icv_counter + 1,
                ]);
                $log->update([
                    'status' => $successStatus,
                    'response_payload' => $response->body(),
                    'cleared_at' => now(),
                ]);

                return ['ok' => true, 'status' => $successStatus, 'message' => $isB2b ? __('Invoice cleared by ZATCA.') : __('Invoice reported to ZATCA.')];
            }

            $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 2000);
            $invoice->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
            $log->update(['status' => 'failed', 'response_payload' => $response->body(), 'error_message' => $errorMessage]);

            return ['ok' => false, 'status' => 'failed', 'message' => $errorMessage];
        });
    }

    /**
     * Same eager-chaining shape as buildUnsignedXml() above, for a Credit
     * Note. A credit note is not a separate chain — it's the next link in
     * the SAME company-wide ICV/PIH sequence as regular invoices, so the
     * caller (CreditNoteController::chainZatcaCreditNote()) must pass in
     * whichever invoice/credit-note/debit-note was submitted most
     * recently for this company, exactly like InvoiceController::
     * chainZatca() already does for invoices.
     *
     * @return array{0: string, 1: string} [unsignedXml, hashBase64]
     */
    public function buildUnsignedCreditNoteXml(CreditNote $creditNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice, string $uuid, int $icv, string $previousHash): array
    {
        $profile = $this->xml->isB2bEligible($client) ? self::STANDARD_PROFILE : self::SIMPLIFIED_PROFILE;
        $unsignedXml = $this->xml->generateForCreditNote($creditNote, $company, $client, $items, $originalInvoice, $profile, $previousHash, $uuid, $icv);
        $hash = $this->signer->contentHash($unsignedXml);

        return [$unsignedXml, $hash];
    }

    /** Debit Note equivalent of buildUnsignedCreditNoteXml() above. */
    public function buildUnsignedDebitNoteXml(DebitNote $debitNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice, string $uuid, int $icv, string $previousHash): array
    {
        $profile = $this->xml->isB2bEligible($client) ? self::STANDARD_PROFILE : self::SIMPLIFIED_PROFILE;
        $unsignedXml = $this->xml->generateForDebitNote($debitNote, $company, $client, $items, $originalInvoice, $profile, $previousHash, $uuid, $icv);
        $hash = $this->signer->contentHash($unsignedXml);

        return [$unsignedXml, $hash];
    }

    /**
     * Submits a credit note's already-computed hash-chain data (set by
     * CreditNoteController::chainZatcaCreditNote() at creation) to
     * ZATCA's clearance/reporting endpoint — same pattern as
     * submitInvoice() above, writing the outcome back onto the credit
     * note (and, on success, the company's zatca_last_invoice_hash, so
     * whatever gets submitted next — invoice, credit note, or debit
     * note — continues from here).
     *
     * @return array{ok: bool, status: string, message: string}
     */
    public function submitCreditNote(CreditNote $creditNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice): array
    {
        if (empty($creditNote->zatca_uuid) || empty($creditNote->zatca_hash)) {
            return ['ok' => false, 'status' => 'failed', 'message' => __('This credit note has no ZATCA chain data.')];
        }

        return $this->lockedSubmission((int) $company->id, function (Company $company) use ($creditNote, $client, $items, $originalInvoice) {
            if (! $company->isZatcaOnboarded()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('ZATCA Phase 2 onboarding is not complete for this company yet.')];
            }

            // Audit finding ZATCA-03 — see submitInvoice()'s identical comment.
            $creditNote = $creditNote->fresh();

            if ($creditNote->isZatcaLocked()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('This credit note has already been cleared or reported to ZATCA by a concurrent request.')];
            }
            if (empty($creditNote->zatca_uuid) || empty($creditNote->zatca_hash)) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('This credit note has no ZATCA chain data.')];
            }

            [$unsignedXml] = $this->buildUnsignedCreditNoteXml(
                $creditNote, $company, $client, $items, $originalInvoice,
                (string) $creditNote->zatca_uuid, (int) $creditNote->zatca_icv, (string) $creditNote->zatca_previous_hash,
            );

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            [$xmlToSubmit] = $this->buildSignedPayload(
                $company, $unsignedXml, (string) $creditNote->zatca_hash, (string) $csid,
                $creditNote->created_at ?? now(), (float) $creditNote->total, (float) $creditNote->vat_amount,
            );

            $xmlBase64 = base64_encode($xmlToSubmit);

            $isB2b = $this->xml->isB2bEligible($client);
            $successStatus = $isB2b ? 'cleared' : 'reported';

            $log = ZatcaCreditNoteLog::create([
                'company_id' => $company->id,
                'credit_note_id' => $creditNote->id,
                'environment' => $company->zatca_environment,
                'invoice_type' => $isB2b ? 'b2b' : 'b2c',
                'direction' => $isB2b ? 'clearance' : 'reporting',
                'status' => 'queued',
                'request_uuid' => $creditNote->zatca_uuid,
                'invoice_hash' => $creditNote->zatca_hash,
                'previous_invoice_hash' => $creditNote->zatca_previous_hash,
                'xml_payload' => $xmlToSubmit,
                'submitted_at' => now(),
            ]);

            try {
                $response = $isB2b
                    ? $this->api->clearInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $creditNote->zatca_hash, (string) $creditNote->zatca_uuid,
                    )
                    : $this->api->reportInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $creditNote->zatca_hash, (string) $creditNote->zatca_uuid,
                    );
            } catch (\Throwable $e) {
                $errorMessage = Str::limit($e->getMessage(), 2000);
                $creditNote->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
                $log->update(['status' => 'failed', 'error_message' => $errorMessage]);

                return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
            }

            if ($response->successful()) {
                $creditNote->update([
                    'zatca_status' => $successStatus,
                    'zatca_submitted_at' => now(),
                    'zatca_response' => $response->body(),
                ]);
                $company->update([
                    'zatca_last_invoice_hash' => $creditNote->zatca_hash,
                    'zatca_last_sync_at' => now(),
                    'zatca_icv_counter' => $company->zatca_icv_counter + 1,
                ]);
                $log->update([
                    'status' => $successStatus,
                    'response_payload' => $response->body(),
                    'cleared_at' => now(),
                ]);

                return ['ok' => true, 'status' => $successStatus, 'message' => $isB2b ? __('Credit note cleared by ZATCA.') : __('Credit note reported to ZATCA.')];
            }

            $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 2000);
            $creditNote->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
            $log->update(['status' => 'failed', 'response_payload' => $response->body(), 'error_message' => $errorMessage]);

            return ['ok' => false, 'status' => 'failed', 'message' => $errorMessage];
        });
    }

    /** Debit Note equivalent of submitCreditNote() above. */
    public function submitDebitNote(DebitNote $debitNote, Company $company, ?Client $client, array $items, Invoice $originalInvoice): array
    {
        if (empty($debitNote->zatca_uuid) || empty($debitNote->zatca_hash)) {
            return ['ok' => false, 'status' => 'failed', 'message' => __('This debit note has no ZATCA chain data.')];
        }

        return $this->lockedSubmission((int) $company->id, function (Company $company) use ($debitNote, $client, $items, $originalInvoice) {
            if (! $company->isZatcaOnboarded()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('ZATCA Phase 2 onboarding is not complete for this company yet.')];
            }

            // Audit finding ZATCA-03 — see submitInvoice()'s identical comment.
            $debitNote = $debitNote->fresh();

            if ($debitNote->isZatcaLocked()) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('This debit note has already been cleared or reported to ZATCA by a concurrent request.')];
            }
            if (empty($debitNote->zatca_uuid) || empty($debitNote->zatca_hash)) {
                return ['ok' => false, 'status' => 'failed', 'message' => __('This debit note has no ZATCA chain data.')];
            }

            [$unsignedXml] = $this->buildUnsignedDebitNoteXml(
                $debitNote, $company, $client, $items, $originalInvoice,
                (string) $debitNote->zatca_uuid, (int) $debitNote->zatca_icv, (string) $debitNote->zatca_previous_hash,
            );

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            [$xmlToSubmit] = $this->buildSignedPayload(
                $company, $unsignedXml, (string) $debitNote->zatca_hash, (string) $csid,
                $debitNote->created_at ?? now(), (float) $debitNote->total, (float) $debitNote->vat_amount,
            );

            $xmlBase64 = base64_encode($xmlToSubmit);

            $isB2b = $this->xml->isB2bEligible($client);
            $successStatus = $isB2b ? 'cleared' : 'reported';

            $log = ZatcaDebitNoteLog::create([
                'company_id' => $company->id,
                'debit_note_id' => $debitNote->id,
                'environment' => $company->zatca_environment,
                'invoice_type' => $isB2b ? 'b2b' : 'b2c',
                'direction' => $isB2b ? 'clearance' : 'reporting',
                'status' => 'queued',
                'request_uuid' => $debitNote->zatca_uuid,
                'invoice_hash' => $debitNote->zatca_hash,
                'previous_invoice_hash' => $debitNote->zatca_previous_hash,
                'xml_payload' => $xmlToSubmit,
                'submitted_at' => now(),
            ]);

            try {
                $response = $isB2b
                    ? $this->api->clearInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $debitNote->zatca_hash, (string) $debitNote->zatca_uuid,
                    )
                    : $this->api->reportInvoice(
                        $company->zatca_environment, (string) $csid, (string) $secret,
                        $xmlBase64, (string) $debitNote->zatca_hash, (string) $debitNote->zatca_uuid,
                    );
            } catch (\Throwable $e) {
                $errorMessage = Str::limit($e->getMessage(), 2000);
                $debitNote->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
                $log->update(['status' => 'failed', 'error_message' => $errorMessage]);

                return ['ok' => false, 'status' => 'failed', 'message' => $e->getMessage()];
            }

            if ($response->successful()) {
                $debitNote->update([
                    'zatca_status' => $successStatus,
                    'zatca_submitted_at' => now(),
                    'zatca_response' => $response->body(),
                ]);
                $company->update([
                    'zatca_last_invoice_hash' => $debitNote->zatca_hash,
                    'zatca_last_sync_at' => now(),
                    'zatca_icv_counter' => $company->zatca_icv_counter + 1,
                ]);
                $log->update([
                    'status' => $successStatus,
                    'response_payload' => $response->body(),
                    'cleared_at' => now(),
                ]);

                return ['ok' => true, 'status' => $successStatus, 'message' => $isB2b ? __('Debit note cleared by ZATCA.') : __('Debit note reported to ZATCA.')];
            }

            $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 2000);
            $debitNote->update(['zatca_status' => 'failed', 'zatca_response' => $errorMessage]);
            $log->update(['status' => 'failed', 'response_payload' => $response->body(), 'error_message' => $errorMessage]);

            return ['ok' => false, 'status' => 'failed', 'message' => $errorMessage];
        });
    }
}
