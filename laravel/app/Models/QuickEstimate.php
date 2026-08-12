<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuickEstimate extends Model
{
    protected $table = 'quick_estimates';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_area' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(QuickEstimateRegion::class, 'region_id');
    }

    public function foundation(): BelongsTo
    {
        return $this->belongsTo(QuickEstimateFoundation::class, 'foundation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function addons(): array
    {
        return json_decode((string) $this->attributes['addons_json'], true) ?: [];
    }
}
