<?php

namespace App\Models;


class QuickEstimateFoundation extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_per_sqm' => 'decimal:2',
        ];
    }
}
