<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'qty_on_hand' => 'decimal:2',
            'reorder_level' => 'decimal:2',
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

    public function stockMovements(): HasMany
    {
        return $this->hasMany(MaterialStockMovement::class);
    }

    /** True only when a reorder_level has actually been set and on-hand qty has fallen to or below it — no false alarms for materials nobody's bothered to set a threshold for. */
    public function isLowStock(): bool
    {
        return $this->reorder_level !== null && (float) $this->qty_on_hand <= (float) $this->reorder_level;
    }
}
