<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorBill extends Model
{
    public const CATEGORIES = ['material' => 'Material', 'labor' => 'Labor', 'equipment' => 'Equipment', 'subcontractor' => 'Subcontractor', 'other' => 'Other'];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'bill_date' => 'date:Y-m-d',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
