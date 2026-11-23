<?php

namespace App\Models;


class QuickEstimateQualityTier extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'multiplier' => 'decimal:2',
        ];
    }
}
