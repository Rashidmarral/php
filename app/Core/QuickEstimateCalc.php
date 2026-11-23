<?php

namespace App\Core;

/** Pricing logic shared by the public Quick Estimate calculator and the in-app version. */
class QuickEstimateCalc
{
    /**
     * @param array<int, array<string, mixed>> $addonRows
     * @return array{addons_payload: array<int, array{id:int,name_en:string,name_ar:string,cost:float}>, subtotal: float, discount_amount: float, vat_amount: float, total: float}
     */
    public static function compute(array $region, array $foundation, array $addonRows, float $totalArea, float $discountPercent, float $vatRate): array
    {
        $baseCost = $totalArea * (float) $region['price_per_sqm'];
        $foundationCost = $totalArea * (float) $foundation['price_per_sqm'];

        $addonCost = 0.0;
        $addonsPayload = [];
        foreach ($addonRows as $addon) {
            $cost = (float) $addon['unit_price'] * $totalArea;
            $addonCost += $cost;
            $addonsPayload[] = ['id' => (int) $addon['id'], 'name_en' => $addon['name_en'], 'name_ar' => $addon['name_ar'], 'cost' => $cost];
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
            $items[] = [
                'description' => $addon[$nameKey] ?? $addon['name_en'],
                'qty' => $estimate['total_area'],
                'unit_price' => (float) $addon['cost'] / max(1, $estimate['total_area']),
                'total' => $addon['cost'],
            ];
        }
        return $items;
    }
}
