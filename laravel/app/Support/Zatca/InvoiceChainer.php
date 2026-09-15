<?php

namespace App\Support\Zatca;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;

/**
 * Populates the ZATCA UUID/ICV/hash-chain fields for a newly created
 * invoice. Chaining is eager (advanced at creation time, not only once
 * an invoice is actually submitted to ZATCA) — this mirrors BuildXact's
 * original (pre-port) behaviour rather than Daftri's own
 * ZatcaSyncService (which only advances company.zatca_last_invoice_hash
 * on a successful clearance/reporting call): BuildXact has no pending-
 * submission queue/admin batch-sync screen, so every invoice is assumed
 * to eventually be submitted in creation order. The hash itself uses the
 * cryptographically correct XAdES content hash (ZatcaXadesSigner::contentHash(),
 * via ZatcaSyncService::buildUnsignedXml()) instead of a naive whole-XML hash.
 *
 * Extracted out of InvoiceController (which used to keep this as a private
 * method) so App\Models\RecurringInvoice::generateInvoice() can put a
 * generated invoice through the exact same chain — a recurring invoice is a
 * real invoice in every respect, including ZATCA compliance, and must
 * continue the SAME company-wide ICV/PIH sequence as hand-entered ones,
 * not a parallel one.
 */
class InvoiceChainer
{
    public static function chain(Invoice $invoice, ?Company $company, ?Client $client, array $items, ZatcaSyncService $zatcaSync): void
    {
        if (!$company) {
            return;
        }
        $uuid = (new ZatcaXmlGenerator())->newUuid();
        $icv = (int) ($company->zatca_last_icv ?? 0) + 1;
        $previousHash = $company->zatca_last_invoice_hash ?: $zatcaSync->genesisHash();

        [, $hash] = $zatcaSync->buildUnsignedXml($invoice, $company, $client, $items, $uuid, $icv, $previousHash);

        $invoice->update([
            'zatca_uuid' => $uuid,
            'zatca_icv' => $icv,
            'zatca_hash' => $hash,
            'zatca_previous_hash' => $previousHash,
        ]);
        $company->update([
            'zatca_last_icv' => $icv,
            'zatca_last_invoice_hash' => $hash,
        ]);
    }
}
