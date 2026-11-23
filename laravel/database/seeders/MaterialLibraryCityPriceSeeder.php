<?php

namespace Database\Seeders;

use App\Models\MaterialLibraryItem;
use App\Models\MaterialLibraryItemCityPrice;
use Illuminate\Database\Seeder;

/**
 * City-wise price overrides for a representative slice of the shared material
 * library (see database/data/material_library_items.php), so the admin's
 * "material price city wise" tooling (MaterialLibraryAdminController::cityPrices())
 * has real reference data out of the box instead of an empty table.
 *
 * Only material_cost is ever overridden by city (see
 * MaterialLibraryItem::effectivePriceFor()) — labor_cost is unaffected, so
 * the Labor category is intentionally excluded here.
 *
 * Each city's price is this item's base material_cost times a fixed regional
 * multiplier, not independently hand-typed, so the data stays internally
 * consistent: Riyadh is the 1.00 baseline; coastal/logistics-hub cities with
 * port access (Jeddah, Dammam, Khobar, Al Jubail) run slightly cheaper for
 * imported materials; Makkah/Madinah carry a demand/access-restriction
 * premium; remote regional cities (Tabuk, Abha, Jizan, Hail, Taif) run
 * higher due to transport/logistics distance from the main supply hubs.
 */
class MaterialLibraryCityPriceSeeder extends Seeder
{
    /** @var array<string, float> */
    private const CITY_MULTIPLIERS = [
        'Riyadh' => 1.00,
        'Jeddah' => 0.97,
        'Dammam' => 0.96,
        'Khobar' => 0.98,
        'Al Jubail' => 0.95,
        'Makkah' => 1.08,
        'Madinah' => 1.06,
        'Taif' => 1.04,
        'Tabuk' => 1.12,
        'Abha' => 1.10,
        'Jizan' => 1.09,
        'Hail' => 1.11,
    ];

    /** A representative slice spanning every material category (Labor excluded — see class docblock). */
    private const SKUS = [
        'CEM-425', 'CEM-SRC', 'REBAR-12', 'REBAR-16', 'RMX-C25', 'RMX-C30',
        'BLK-CONC20', 'BLK-CONC15', 'BLK-AAC', 'BRICK-CLAY',
        'TILE-CER60', 'TILE-POR80', 'TILE-GRAN60', 'MARBLE-SLAB', 'GRANITE-SLAB', 'TILEADH-C2',
        'PVC-110', 'PVC-160', 'PPR-20', 'WATERHEATER-50',
        'CABLE-25', 'CABLE-40', 'MCB-20A', 'LED-DOWNLIGHT',
        'AC-SPLIT18', 'AC-SPLIT24', 'AC-DUCTED5T',
        'PAINT-EMLINT', 'PAINT-EMLEXT',
        'DOOR-FLUSHINT', 'WIN-ALUSLD', 'WIN-UPVCDG',
        'WPMEMB-BIT', 'INSUL-XPS5',
        'SAND-FINE', 'SAND-COARSE', 'AGG-CRUSH20', 'BASECOURSE',
        'WC-WALLHUNG', 'BASIN-COUNTER', 'KITCHENSINK-SS',
        'GLASS-TEMP10', 'ALUCOMPOSITE', 'ALUPROFILE-WIN',
        'GYPBOARD-STD',
    ];

    public function run(): void
    {
        $items = MaterialLibraryItem::whereIn('sku', self::SKUS)->get()->keyBy('sku');

        $rows = 0;
        foreach (self::SKUS as $sku) {
            $item = $items[$sku] ?? null;
            if (! $item) {
                continue;
            }
            foreach (self::CITY_MULTIPLIERS as $city => $multiplier) {
                MaterialLibraryItemCityPrice::updateOrCreate(
                    ['material_library_item_id' => $item->id, 'city' => $city],
                    ['unit_cost' => round((float) $item->material_cost * $multiplier, 2), 'source' => 'manual']
                );
                $rows++;
            }
        }

        $this->command?->info($rows.' material library city prices seeded/updated across '.count(self::CITY_MULTIPLIERS).' cities.');
    }
}
