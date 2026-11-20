<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ingredient of an Assembly. qty_per_unit is how much of this item is
 * needed per 1 unit of the parent Assembly's base_uom — when a user applies
 * the assembly at some quantity, the real estimate line item gets
 * qty = round(qty_per_unit * applied_qty, 2). cost_code_id is a loose,
 * unenforced FK, same style as estimate_items.cost_code_id.
 */
class AssemblyItem extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty_per_unit' => 'decimal:4',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }

    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class);
    }
}
