<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'retention_released' => 'boolean',
            'retention_released_at' => 'datetime:Y-m-d H:i:s',
            'due_date' => 'date:Y-m-d',
            'zatca_submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoicePayments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    public function debitNotes(): HasMany
    {
        return $this->hasMany(DebitNote::class);
    }

    public function creditedTotal(): float
    {
        return (float) $this->creditNotes()->where('status', 'issued')->sum('total');
    }

    public function debitedTotal(): float
    {
        return (float) $this->debitNotes()->where('status', 'issued')->sum('total');
    }

    /** How much of this invoice's total can still be covered by a new Credit Note — prevents over-crediting across multiple partial credit notes. */
    public function remainingCreditableTotal(): float
    {
        return round((float) $this->total - $this->creditedTotal(), 2);
    }

    /**
     * True once ZATCA has cleared (B2B) or reported (B2C) this invoice —
     * at that point the document is part of an immutable tax record and
     * can no longer be edited or deleted. The only compliant way to
     * correct a locked invoice is a Credit Note (or a Debit Note, for an
     * under-billed charge) referencing it.
     */
    public function isZatcaLocked(): bool
    {
        return in_array($this->zatca_status, ['cleared', 'reported'], true);
    }
}
