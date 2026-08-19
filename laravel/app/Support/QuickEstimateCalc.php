<?php

namespace App\Support;

/** Pricing logic shared by the public Quick Estimate calculator and the in-app version. */
class QuickEstimateCalc
{
    /**
     * @param array<int, array<string, mixed>> $addonRows
     * @param array<int, float> $addonQuantities Manual quantities keyed by addon id, used for addons whose qty_mode isn't 'area'.
     * @return array{addons_payload: array<int, array{id:int,name_en:string,name_ar:string,cost:float,qty:float,unit_type:string}>, subtotal: float, discount_amount: float, vat_amount: float, total: float}
     */
    public static function compute(array $region, array $foundation, array $addonRows, float $totalArea, float $discountPercent, float $vatRate, array $addonQuantities = []): array
    {
        $baseCost = $totalArea * (float) $region['price_per_sqm'];
        $foundationCost = $totalArea * (float) $foundation['price_per_sqm'];

        $addonCost = 0.0;
        $addonsPayload = [];
        foreach ($addonRows as $addon) {
            // Area-priced add-ons (e.g. SAR/m²) scale automatically with the total area.
            // Everything else (ton, unit, linear m, m³...) uses the quantity the user entered for that add-on.
            $isAreaBased = ($addon['qty_mode'] ?? 'area') === 'area';
            $qty = $isAreaBased ? $totalArea : max(0, (float) ($addonQuantities[(int) $addon['id']] ?? 1));
            $cost = (float) $addon['unit_price'] * $qty;
            $addonCost += $cost;
            $addonsPayload[] = [
                'id' => (int) $addon['id'],
                'name_en' => $addon['name_en'],
                'name_ar' => $addon['name_ar'],
                'cost' => $cost,
                'qty' => $qty,
                'unit_type' => (string) ($addon['unit_type'] ?? ''),
            ];
        }

        $multiplier = (float) ($region['multiplier'] ?? 1.0) ?: 1.0;
        $subtotal = ($baseCost + $foundationCost + $addonCost) * $multiplier;
        $discountAmount = $subtotal * $discountPercent / 100;
        $taxable = $subtotal - $discountAmount;
        $vatAmount = $taxable * $vatRate / 100;
        $total = $taxable + $vatAmount;

        return [
            'addons_payload' => $addonsPayload,
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'vat_amount' => $vatAmount,
            'total' => $total,
        ];
    }

    /** @return array<int, array{description:string,qty:float,unit_price:float,total:float}> */
    public static function pdfItems(array $estimate, ?array $region, ?array $foundation, array $addons, string $lang): array
    {
        $items = [];
        $nameKey = $lang === 'ar' ? 'name_ar' : 'name_en';

        if ($region) {
            $items[] = [
                'description' => ($lang === 'ar' ? 'التكلفة الأساسية للبناء' : 'Base construction cost') . ' — ' . $region[$nameKey],
                'qty' => $estimate['total_area'],
                'unit_price' => $region['price_per_sqm'],
                'total' => $estimate['total_area'] * $region['price_per_sqm'],
            ];
        }
        if ($foundation) {
            $items[] = [
                'description' => ($lang === 'ar' ? 'الأساسات' : 'Foundation') . ' — ' . $foundation[$nameKey],
                'qty' => $estimate['total_area'],
                'unit_price' => $foundation['price_per_sqm'],
                'total' => $estimate['total_area'] * $foundation['price_per_sqm'],
            ];
        }
        foreach ($addons as $addon) {
            $qty = (float) ($addon['qty'] ?? $estimate['total_area']);
            $items[] = [
                'description' => $addon[$nameKey] ?? $addon['name_en'],
                'qty' => $qty,
                'unit_price' => (float) $addon['cost'] / max(0.0001, $qty),
                'total' => $addon['cost'],
            ];
        }
        return $items;
    }
}
