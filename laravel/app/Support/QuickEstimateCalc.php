<?php

namespace App\Support;

/** Pricing logic shared by the public Quick Estimate calculator and the in-app version. */
class QuickEstimateCalc
{
    /**
     * @param array<int, array<string, mixed>> $addonRows
     * @param array<int, float> $addonQuantities Manual quantities keyed by addon id, used for addons whose qty_mode isn't 'area'.
     * @param array<string, mixed> $qualityTier Optional finish tier (economy/standard/premium); empty array means an implicit 1.0 multiplier.
     * @return array{addons_payload: array<int, array{id:int,name_en:string,name_ar:string,cost:float,qty:float,unit_type:string}>, subtotal: float, discount_amount: float, vat_amount: float, total: float}
     */
    public static function compute(array $region, array $foundation, array $addonRows, float $totalArea, float $discountPercent, float $vatRate, array $addonQuantities = [], array $qualityTier = []): array
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

        $regionMultiplier = (float) ($region['multiplier'] ?? 1.0) ?: 1.0;
        $tierMultiplier = (float) ($qualityTier['multiplier'] ?? 1.0) ?: 1.0;
        $multiplier = $regionMultiplier * $tierMultiplier;
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

    /**
     * @param array<string, mixed>|null $qualityTier Optional finish tier — its multiplier (like the region's) applies
     *     to the whole subtotal rather than being its own cost, so it's folded into each line item's displayed unit
     *     price here (mirroring how EstimateController::sellPricedItems() scales prices by a factor to reconcile
     *     with a total) instead of being tacked on as a separate, unreconciled line.
     * @return array<int, array{description:string,qty:float,unit_price:float,total:float}>
     */
    public static function pdfItems(array $estimate, ?array $region, ?array $foundation, array $addons, string $lang, ?array $qualityTier = null): array
    {
        $items = [];
        $nameKey = $lang === 'ar' ? 'name_ar' : 'name_en';

        $regionMultiplier = (float) ($region['multiplier'] ?? 1.0) ?: 1.0;
        $tierMultiplier = (float) ($qualityTier['multiplier'] ?? 1.0) ?: 1.0;
        $factor = $regionMultiplier * $tierMultiplier;
        $tierNote = ($qualityTier && $tierMultiplier !== 1.0) ? ' (' . $qualityTier[$nameKey] . ')' : '';

        if ($region) {
            $items[] = [
                'description' => ($lang === 'ar' ? 'التكلفة الأساسية للبناء' : 'Base construction cost') . ' — ' . $region[$nameKey] . $tierNote,
                'qty' => $estimate['total_area'],
                'unit_price' => $region['price_per_sqm'] * $factor,
                'total' => $estimate['total_area'] * $region['price_per_sqm'] * $factor,
            ];
        }
        if ($foundation) {
            $items[] = [
                'description' => ($lang === 'ar' ? 'الأساسات' : 'Foundation') . ' — ' . $foundation[$nameKey],
                'qty' => $estimate['total_area'],
                'unit_price' => $foundation['price_per_sqm'] * $factor,
                'total' => $estimate['total_area'] * $foundation['price_per_sqm'] * $factor,
            ];
        }
        foreach ($addons as $addon) {
            $qty = (float) ($addon['qty'] ?? $estimate['total_area']);
            $total = (float) $addon['cost'] * $factor;
            $items[] = [
                'description' => $addon[$nameKey] ?? $addon['name_en'],
                'qty' => $qty,
                'unit_price' => $total / max(0.0001, $qty),
                'total' => $total,
            ];
        }
        return $items;
    }
}
