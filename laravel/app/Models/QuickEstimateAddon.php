<?php

namespace App\Models;


class QuickEstimateAddon extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_pro' => 'boolean',
            'unit_price' => 'decimal:2',
        ];
    }
}
