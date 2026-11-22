<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function cityPrices(): HasMany
    {
        return $this->hasMany(MaterialLibraryItemCityPrice::class);
    }

    /**
     * The material cost to use when a company pulls this item into its own materials
     * table: the $city-specific override from material_library_item_city_prices if one
     * exists, else this item's own flat material_cost. labor_cost is never affected by
     * city pricing — city price books are about material cost only.
     */
    public function effectivePriceFor(?string $city): float
    {
        $city = trim((string) $city);
        if ($city === '') {
            return (float) $this->material_cost;
        }

        $override = $this->relationLoaded('cityPrices')
            ? $this->cityPrices->firstWhere('city', $city)
            : $this->cityPrices()->where('city', $city)->first();

        return $override ? (float) $override->unit_cost : (float) $this->material_cost;
    }
}
