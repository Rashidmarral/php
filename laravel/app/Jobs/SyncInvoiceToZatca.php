<?php

namespace App\Jobs;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\Zatca\ZatcaSyncService;
use App\Support\Zatca\ZatcaXmlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Submits one invoice to ZATCA in the background — dispatched right after
 * InvoiceController::store() eagerly chains a newly created invoice's
 * UUID/ICV/hash (see App\Support\Zatca\InvoiceChainer), so an onboarded
 * company's invoices reach ZATCA automatically instead of only when
 * someone clicks the "Submit to ZATCA" button. That button (submitZatca()
 * on InvoiceController/CreditNoteController/DebitNoteController) still
 * calls ZatcaSyncService directly and synchronously — it is BuildXact's
 * manual retry/"Sync now" action, and must keep giving immediate
 * pass/fail feedback rather than being queued.
 *
 * Adapted from Daftri's App\Jobs\SyncInvoiceToZatca: that version also
 * checks Company::isOperational() (whether the company's subscription is
 * suspended) before auto-submitting — BuildXact's Company model has no
 * such method, so that check is simply omitted here, per this port's
 * scope notes, rather than invented.
 */
class SyncInvoiceToZatca implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public readonly int $invoiceId) {}

    public function handle(ZatcaSyncService $sync, ZatcaXmlGenerator $xml): void
    {
        $invoice = Invoice::find($this->invoiceId);

        if (! $invoice) {
            return;
        }

        $company = Company::find($invoice->company_id);

        if (! $company || ! $company->isZatcaOnboarded()) {
            return;
        }

        if ($invoice->isZatcaLocked()) {
            // Already cleared/reported — most likely by the manual
            // "Submit to ZATCA" button winning the race before this
            // queued job ran. Nothing left to do.
            return;
        }

        $client = $invoice->client_id ? Client::find($invoice->client_id) : null;
        $isB2b = $xml->isB2bEligible($client);

        if ($isB2b && ! $company->zatca_sync_b2b) {
            return;
        }
        if (! $isB2b && ! $company->zatca_sync_b2c) {
            return;
        }

        $items = InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get()
            ->map(fn (InvoiceItem $item) => [
                'description' => $item->description,
                'qty' => $item->qty,
                'unit_price' => $item->unit_price,
                'total' => $item->total,
            ])->all();

        $sync->submitInvoice($invoice, $company, $client, $items);
    }
}
