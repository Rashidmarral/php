<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Credit Note (UBL InvoiceTypeCode 381) — the ZATCA-compliant way to
 * reduce what's owed on an already-cleared/reported invoice (a return, a
 * billing error, a price adjustment) without editing the immutable
 * original. See Invoice::isZatcaLocked()/remainingCreditableTotal().
 */
class CreditNote extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'issue_date' => 'date:Y-m-d',
            'zatca_submitted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditNoteItem::class);
    }

    /** Same immutability rule as Invoice::isZatcaLocked() — once cleared/reported, a note can no longer be voided. */
    public function isZatcaLocked(): bool
    {
        return in_array($this->zatca_status, ['cleared', 'reported'], true);
    }
}
