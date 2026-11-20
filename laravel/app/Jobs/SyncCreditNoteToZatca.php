<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Credit Note equivalent of SyncInvoiceToZatca — dispatched right after
 * CreditNoteController::store() chains a newly created credit note, so it
 * reaches ZATCA automatically for an onboarded company. See that job's
 * docblock for why the manual "Submit to ZATCA" button stays synchronous
 * and why there is no Company::isOperational() check here.
 */
class SyncCreditNoteToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $creditNoteId) {}

    public function handle(ZatcaSyncService $sync, ZatcaXmlGenerator $xml): void
    {
        $creditNote = CreditNote::find($this->creditNoteId);

        if (! $creditNote) {
            return;
        }

        $company = Company::find($creditNote->company_id);

        if (! $company || ! $company->isZatcaOnboarded()) {
            return;
        }

        if ($creditNote->isZatcaLocked()) {
            return;
        }

        $originalInvoice = Invoice::find($creditNote->invoice_id);

        if (! $originalInvoice) {
            return;
        }

        $client = $creditNote->client_id ? Client::find($creditNote->client_id) : null;
        $isB2b = $xml->isB2bEligible($client);

        if ($isB2b && ! $company->zatca_sync_b2b) {
            return;
        }
        if (! $isB2b && ! $company->zatca_sync_b2c) {
            return;
        }

        $items = CreditNoteItem::where('credit_note_id', $creditNote->id)->orderBy('id')->get()
            ->map(fn (CreditNoteItem $item) => [
                'description' => $item->description,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])->all();

        $sync->submitCreditNote($creditNote, $company, $client, $items, $originalInvoice);
    }
}
