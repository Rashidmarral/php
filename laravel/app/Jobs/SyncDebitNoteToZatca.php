<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Company;
use App\Models\DebitNote;
use App\Models\DebitNoteItem;
use App\Models\Invoice;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Debit Note equivalent of SyncCreditNoteToZatca — see that job's
 * docblock (and SyncInvoiceToZatca's) for why the manual "Submit to
 * ZATCA" button stays synchronous and why there is no
 * Company::isOperational() check here.
 */
class SyncDebitNoteToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $debitNoteId) {}

    public function handle(ZatcaSyncService $sync, ZatcaXmlGenerator $xml): void
    {
        $debitNote = DebitNote::find($this->debitNoteId);

        if (! $debitNote) {
            return;
        }

        $company = Company::find($debitNote->company_id);

        if (! $company || ! $company->isZatcaOnboarded()) {
            return;
        }

        if ($debitNote->isZatcaLocked()) {
            return;
        }

        $originalInvoice = Invoice::find($debitNote->invoice_id);

        if (! $originalInvoice) {
            return;
        }

        $client = $debitNote->client_id ? Client::find($debitNote->client_id) : null;
        $isB2b = $xml->isB2bEligible($client);

        if ($isB2b && ! $company->zatca_sync_b2b) {
            return;
        }
        if (! $isB2b && ! $company->zatca_sync_b2c) {
            return;
        }

        $items = DebitNoteItem::where('debit_note_id', $debitNote->id)->orderBy('id')->get()
            ->map(fn (DebitNoteItem $item) => [
                'description' => $item->description,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])->all();

        $sync->submitDebitNote($debitNote, $company, $client, $items, $originalInvoice);
    }
}
