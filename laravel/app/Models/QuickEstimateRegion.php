<?php

namespace App\Models;


class QuickEstimateRegion extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_sqm' => 'decimal:2',
            'multiplier' => 'decimal:2',
        ];
    }
}
