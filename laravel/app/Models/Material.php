<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
            'material_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
