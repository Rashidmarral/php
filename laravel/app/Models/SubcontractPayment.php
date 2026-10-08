<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractPayment extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date:Y-m-d',
            'cumulative_value' => 'decimal:2',
            'previous_cumulative_value' => 'decimal:2',
            'this_period_value' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'net_payable' => 'decimal:2',
            'certified_at' => 'datetime',
        ];
    }

    public function subcontract(): BelongsTo
    {
        return $this->belongsTo(Subcontract::class);
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
