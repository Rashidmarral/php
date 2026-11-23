<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChangeOrder extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'time_impact_days' => 'integer',
            'approved_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChangeOrderItem::class);
    }

    /**
     * Keeps `amount` in sync with the sum of this change order's line items —
     * but ONLY once it actually has any. See ApprovalChain::startIfChained()'s
     * opening docblock for the same "an empty collection means nothing has
     * opted in yet" rule this follows: a change order with zero rows in
     * change_order_items — every change order created before line items
     * existed, and any company that keeps using the original single-amount
     * flow — is left alone here, so `amount` stays exactly what was typed in
     * on creation, fully manual, with zero behavior change.
     */
    public function syncAmountFromItems(): void
    {
        $items = $this->items()->get();
        if ($items->isEmpty()) {
            return;
        }
        $this->update(['amount' => $items->sum('total')]);
    }
}
