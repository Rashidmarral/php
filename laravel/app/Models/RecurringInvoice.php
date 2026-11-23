<?php

namespace App\Models;

use App\Support\Zatca\InvoiceChainer;
use App\Support\Zatca\ZatcaSyncService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A recurring invoice template — a fixed set of line items plus a
 * schedule (frequency + next_run_date) for a recurring billing need
 * (a maintenance/retainer contract, a lease, a service agreement).
 *
 * App\Console\Commands\RunDailyTasks calls generateInvoice() on every
 * active template whose next_run_date is due. The generated Invoice is a
 * real invoice in every respect — same invoice_number sequence, same
 * subtotal/vat/retention arithmetic as InvoiceController::store(), and
 * the same eager ZATCA hash-chain (InvoiceChainer) — just traced back to
 * its template via invoices.recurring_invoice_id.
 */
class RecurringInvoice extends Model
{
    public const FREQUENCIES = [
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'yearly' => 'Yearly',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'next_run_date' => 'date:Y-m-d',
            'last_generated_at' => 'datetime',
            'is_active' => 'boolean',
            'apply_vat' => 'boolean',
            'retention_percent' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RecurringInvoiceItem::class);
    }

    /** Every real Invoice this template has generated so far, most recent first. */
    public function generatedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderByDesc('id');
    }

    /** The next scheduled date after $date, advanced by exactly one frequency period. */
    public function nextRunAfter(\DateTimeInterface $date): \Carbon\Carbon
    {
        $date = \Carbon\Carbon::instance(\Carbon\Carbon::parse($date));

        return match ($this->frequency) {
            'weekly' => $date->addWeek(),
            'quarterly' => $date->addQuarter(),
            'yearly' => $date->addYear(),
            default => $date->addMonth(),
        };
    }

    /**
     * Generates a real Invoice (+ InvoiceItem rows) from this template's
     * current line items, chains it into the company's ZATCA sequence
     * exactly like a hand-entered invoice, and advances next_run_date /
     * last_generated_at — all inside one transaction, so a crash mid-way
     * can never leave next_run_date advanced without the invoice existing,
     * or vice versa (this is what makes RunDailyTasks safe to run twice in
     * one day: the second run's next_run_date <= today query no longer
     * matches this template once the transaction has committed).
     */
    public function generateInvoice(ZatcaSyncService $zatcaSync): Invoice
    {
        return DB::transaction(function () use ($zatcaSync) {
            $companyId = $this->company_id;

            $subtotal = 0;
            $items = [];
            foreach ($this->items()->orderBy('id')->get() as $templateItem) {
                $qty = (float) $templateItem->qty;
                $price = (float) $templateItem->unit_price;
                $lineTotal = $qty * $price;
                $subtotal += $lineTotal;
                $items[] = [
                    'description' => $templateItem->description,
                    'description_ar' => (string) ($templateItem->description_ar ?? ''),
                    'qty' => $qty,
                    'unit_price' => $price,
                    'total' => $lineTotal,
                ];
            }

            $applyVat = (bool) $this->apply_vat;
            $vatRate = $applyVat ? (float) Setting::get('vat_rate', '15') : 0;
            $vatAmount = $subtotal * $vatRate / 100;
            $total = $subtotal + $vatAmount;

            $retentionPercent = min(100, max(0, (float) $this->retention_percent));
            $retentionAmount = $subtotal * $retentionPercent / 100;

            $invoice = Invoice::create([
                'company_id' => $companyId,
                'project_id' => $this->project_id,
                'client_id' => $this->client_id,
                'recurring_invoice_id' => $this->id,
                'invoice_number' => 'INV-' . (1000 + Invoice::where('company_id', $companyId)->count() + 1),
                'status' => 'unpaid',
                'total' => $total,
                'vat_rate' => $vatRate,
                'vat_amount' => $vatAmount,
                'due_date' => now()->addDays((int) $this->due_days)->format('Y-m-d'),
                'retention_percent' => $retentionPercent,
                'retention_amount' => $retentionAmount,
                'share_token' => bin2hex(random_bytes(20)),
            ]);

            foreach ($items as $item) {
                InvoiceItem::create(['invoice_id' => $invoice->id, ...$item]);
            }

            $company = Company::find($companyId);
            $client = $this->client_id ? Client::find($this->client_id) : null;
            InvoiceChainer::chain($invoice->fresh(), $company, $client, $items, $zatcaSync);

            $this->update([
                'next_run_date' => $this->nextRunAfter($this->next_run_date)->format('Y-m-d'),
                'last_generated_at' => now(),
            ]);

            return $invoice->fresh();
        });
    }
}
