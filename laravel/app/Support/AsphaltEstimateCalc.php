<?php

namespace App\Support;

/**
 * Pure quantity/cost math for the Asphalt / Road Estimate tool — no DB, no
 * HTTP (same style as CriticalPathCalculator). AsphaltEstimateController is
 * the only caller: it reads the submitted segment + other-cost arrays,
 * hands them here, and persists exactly what comes back.
 *
 * Rounding convention (documented once, applied consistently): every
 * *_m2/*_m3 area/volume is rounded to 3 decimals (matching the
 * asphalt_estimate_segments.*_volume_m3 columns' decimal(12,3)), every
 * *_tons/*_cost/*_total monetary or mass value is rounded to 2 decimals.
 * computeSegment() rounds its own outputs so every value already matches
 * what gets stored; computeTotals() does the same for the parent-level
 * sums, and defers markup/tax/total entirely to EstimateCalc::compute() —
 * the single source of truth for that math, reused rather than duplicated.
 *
 * Per-segment geometry (all inputs in meters/millimeters):
 *   net_width_m           = max(0, width_m - median_width_m)
 *   area_m2                = length_m * net_width_m
 *   subbase_volume_m3      = area_m2 * (subbase_thickness_mm / 1000)
 *   base_course_volume_m3  = area_m2 * (base_course_thickness_mm / 1000)
 *   asphalt_volume_m3      = area_m2 * ((asphalt_binder_thickness_mm + asphalt_wearing_thickness_mm) / 1000)
 *   asphalt_tons           = asphalt_volume_m3 * (asphalt_density_kg_per_m3 / 1000)
 *   subbase_cost           = subbase_volume_m3 * subbase_unit_cost_per_m3
 *   base_course_cost       = base_course_volume_m3 * base_course_unit_cost_per_m3
 *   asphalt_cost           = asphalt_tons * asphalt_unit_cost_per_ton
 *   segment_total          = subbase_cost + base_course_cost + asphalt_cost
 *
 * Overall estimate:
 *   segments_subtotal   = sum(segment_total)
 *   total_asphalt_tons  = sum(asphalt_tons)
 *   total_area_m2       = sum(area_m2)
 *   transport_cost      = total_asphalt_tons * transport_cost_per_ton
 *   paver_cost          = paver_daily_rate * paver_days
 *   roller_cost         = roller_daily_rate * roller_days
 *   labor_cost          = labor_crew_size * labor_days * labor_daily_rate_per_worker
 *   labor_food_cost     = labor_crew_size * labor_days * labor_food_daily_rate_per_worker
 *   other_costs_subtotal = transport_cost + paver_cost + roller_cost + labor_cost + labor_food_cost
 *                          + surveyor_fee + lab_testing_fee + mobilization_fee
 *   subtotal            = segments_subtotal + other_costs_subtotal
 *   [markup_amount, tax_amount, total] = EstimateCalc::compute(subtotal, markup_percent, tax_percent)
 */
class AsphaltEstimateCalc
{
    /**
     * @param  array{length_m: float, width_m: float, median_width_m: float, subbase_thickness_mm: float, subbase_unit_cost_per_m3: float, base_course_thickness_mm: float, base_course_unit_cost_per_m3: float, asphalt_binder_thickness_mm: float, asphalt_wearing_thickness_mm: float, asphalt_density_kg_per_m3: float, asphalt_unit_cost_per_ton: float}  $segment
     * @return array{net_width_m: float, area_m2: float, subbase_volume_m3: float, base_course_volume_m3: float, asphalt_volume_m3: float, asphalt_tons: float, subbase_cost: float, base_course_cost: float, asphalt_cost: float, segment_total: float}
     */
    public static function computeSegment(array $segment): array
    {
        $netWidthM = max(0.0, (float) $segment['width_m'] - (float) $segment['median_width_m']);
        $areaM2 = (float) $segment['length_m'] * $netWidthM;

        $subbaseVolumeM3 = $areaM2 * ((float) $segment['subbase_thickness_mm'] / 1000);
        $baseCourseVolumeM3 = $areaM2 * ((float) $segment['base_course_thickness_mm'] / 1000);
        $asphaltThicknessM = ((float) $segment['asphalt_binder_thickness_mm'] + (float) $segment['asphalt_wearing_thickness_mm']) / 1000;
        $asphaltVolumeM3 = $areaM2 * $asphaltThicknessM;
        $asphaltTons = $asphaltVolumeM3 * ((float) $segment['asphalt_density_kg_per_m3'] / 1000);

        $subbaseCost = $subbaseVolumeM3 * (float) $segment['subbase_unit_cost_per_m3'];
        $baseCourseCost = $baseCourseVolumeM3 * (float) $segment['base_course_unit_cost_per_m3'];
        $asphaltCost = $asphaltTons * (float) $segment['asphalt_unit_cost_per_ton'];
        $segmentTotal = $subbaseCost + $baseCourseCost + $asphaltCost;

        return [
            'net_width_m' => round($netWidthM, 2),
            'area_m2' => round($areaM2, 2),
            'subbase_volume_m3' => round($subbaseVolumeM3, 3),
            'base_course_volume_m3' => round($baseCourseVolumeM3, 3),
            'asphalt_volume_m3' => round($asphaltVolumeM3, 3),
            'asphalt_tons' => round($asphaltTons, 2),
            'subbase_cost' => round($subbaseCost, 2),
            'base_course_cost' => round($baseCourseCost, 2),
            'asphalt_cost' => round($asphaltCost, 2),
            'segment_total' => round($segmentTotal, 2),
        ];
    }

    /**
     * @param  array<int, array{area_m2: float, asphalt_tons: float, segment_total: float}>  $computedSegments  the output of computeSegment() for every segment
     * @param  array{transport_cost_per_ton: float, paver_daily_rate: float, paver_days: float, roller_daily_rate: float, roller_days: float, labor_crew_size: float, labor_days: float, labor_daily_rate_per_worker: float, labor_food_daily_rate_per_worker: float, surveyor_fee: float, lab_testing_fee: float, mobilization_fee: float}  $otherCosts
     * @return array{segments_subtotal: float, other_costs_subtotal: float, subtotal: float, total_asphalt_tons: float, total_area_m2: float, transport_cost: float, paver_cost: float, roller_cost: float, labor_cost: float, labor_food_cost: float, markup_amount: float, tax_amount: float, total: float}
     */
    public static function computeTotals(array $computedSegments, array $otherCosts, float $markupPercent, float $taxPercent): array
    {
        $segmentsSubtotal = 0.0;
        $totalAsphaltTons = 0.0;
        $totalAreaM2 = 0.0;
        foreach ($computedSegments as $segment) {
            $segmentsSubtotal += $segment['segment_total'];
            $totalAsphaltTons += $segment['asphalt_tons'];
            $totalAreaM2 += $segment['area_m2'];
        }

        $transportCost = $totalAsphaltTons * (float) $otherCosts['transport_cost_per_ton'];
        $paverCost = (float) $otherCosts['paver_daily_rate'] * (float) $otherCosts['paver_days'];
        $rollerCost = (float) $otherCosts['roller_daily_rate'] * (float) $otherCosts['roller_days'];
        $workerDays = (float) $otherCosts['labor_crew_size'] * (float) $otherCosts['labor_days'];
        $laborCost = $workerDays * (float) $otherCosts['labor_daily_rate_per_worker'];
        $laborFoodCost = $workerDays * (float) $otherCosts['labor_food_daily_rate_per_worker'];

        $otherCostsSubtotal = $transportCost + $paverCost + $rollerCost + $laborCost + $laborFoodCost
            + (float) $otherCosts['surveyor_fee'] + (float) $otherCosts['lab_testing_fee'] + (float) $otherCosts['mobilization_fee'];

        $subtotal = $segmentsSubtotal + $otherCostsSubtotal;
        $calc = EstimateCalc::compute($subtotal, $markupPercent, $taxPercent);

        return [
            'segments_subtotal' => round($segmentsSubtotal, 2),
            'other_costs_subtotal' => round($otherCostsSubtotal, 2),
            'subtotal' => round($subtotal, 2),
            'total_asphalt_tons' => round($totalAsphaltTons, 2),
            'total_area_m2' => round($totalAreaM2, 2),
            'transport_cost' => round($transportCost, 2),
            'paver_cost' => round($paverCost, 2),
            'roller_cost' => round($rollerCost, 2),
            'labor_cost' => round($laborCost, 2),
            'labor_food_cost' => round($laborFoodCost, 2),
            'markup_amount' => $calc['markup_amount'],
            'tax_amount' => $calc['tax_amount'],
            'total' => $calc['total'],
        ];
    }
}
