<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One city's price override for a MaterialLibraryItem. source is 'manual' or 'csv_import'. */
class MaterialLibraryItemCityPrice extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_cost' => 'decimal:2',
        ];
    }

    public function materialLibraryItem(): BelongsTo
    {
        return $this->belongsTo(MaterialLibraryItem::class);
    }
}
