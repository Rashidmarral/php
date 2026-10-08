<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const STATUSES = [
        'draft' => 'Draft',
        'issued' => 'Issued',
        'received' => 'Received',
        'cancelled' => 'Cancelled',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate_to_sar' => 'decimal:4',
            'issue_date' => 'date:Y-m-d',
            'expected_delivery_date' => 'date:Y-m-d',
        ];
    }

    /**
     * This purchase order's total converted to SAR using its own exchange_rate_to_sar — for a
     * SAR purchase order (the default for every pre-existing row) the rate is exactly 1.0, so
     * this is a complete no-op and simply equals total. Informational/commitment-tracking only
     * — never fed into Project::actualCostTotal()/revisedBudget() or the cost-variance report,
     * which stay keyed on VendorBill.amount alone.
     */
    public function totalInSar(): float
    {
        return (float) $this->total * (float) $this->exchange_rate_to_sar;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** Only a draft PO can still be edited or deleted — once issued it's a real commitment communicated to a supplier. */
    public function isEditable(): bool
    {
        return $this->status === 'draft';
    }
}
