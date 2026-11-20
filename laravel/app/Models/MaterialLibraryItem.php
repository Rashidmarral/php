<?php

namespace App\Models;

class MaterialLibraryItem extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'material_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
