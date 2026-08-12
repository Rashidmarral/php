<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
