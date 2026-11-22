<?php

namespace Tests\Unit;

use App\Support\AsphaltEstimateCalc;
use PHPUnit\Framework\TestCase;

/**
 * Pure-math coverage for AsphaltEstimateCalc — no DB, no HTTP (same style as
 * CriticalPathCalculatorTest). Hand-worked two-segment example below, so the
 * test is self-documenting.
 *
 * Segment "Main Road": length=1000m, width=10m, median=2m
 *   net_width = 10 - 2 = 8m
 *   area = 1000 * 8 = 8000 m²
 *   subbase: 150mm => volume = 8000 * 0.15 = 1200 m³, @80/m³  => cost = 96,000
 *   base course: 200mm => volume = 8000 * 0.20 = 1600 m³, @120/m³ => cost = 192,000
 *   asphalt: binder 50mm + wearing 40mm = 90mm => volume = 8000 * 0.09 = 720 m³
 *     tons = 720 * (2400/1000) = 1728 t, @250/ton => cost = 432,000
 *   segment_total = 96,000 + 192,000 + 432,000 = 720,000
 *
 * Segment "Cross Street 1": length=200m, width=8m, median=0
 *   net_width = 8m, area = 200 * 8 = 1600 m²
 *   subbase: 100mm => volume = 1600 * 0.10 = 160 m³, @80/m³ => cost = 12,800
 *   base course: 150mm => volume = 1600 * 0.15 = 240 m³, @120/m³ => cost = 28,800
 *   asphalt: binder 40mm + wearing 30mm = 70mm => volume = 1600 * 0.07 = 112 m³
 *     tons = 112 * 2.4 = 268.8 t, @250/ton => cost = 67,200
 *   segment_total = 12,800 + 28,800 + 67,200 = 108,800
 *
 * Totals:
 *   segments_subtotal = 720,000 + 108,800 = 828,800
 *   total_asphalt_tons = 1728 + 268.8 = 1996.8
 *   total_area_m2 = 8000 + 1600 = 9600
 *
 * Other costs: transport 15/ton * 1996.8t = 29,952; paver 2000/day * 10d = 20,000;
 * roller 1500/day * 10d = 15,000; labor 20 workers * 10d * 150/worker = 30,000;
 * labor food 20 * 10 * 40 = 8,000; surveyor 5,000; lab testing 3,000;
 * mobilization 10,000.
 *   other_costs_subtotal = 29,952+20,000+15,000+30,000+8,000+5,000+3,000+10,000 = 120,952
 *   subtotal = 828,800 + 120,952 = 949,752
 *
 * markup 10%: markup_amount = 94,975.20, sell_subtotal = 1,044,727.20
 * tax 15%: tax_amount = 156,709.08, total = 1,201,436.28
 */
class AsphaltEstimateCalcTest extends TestCase
{
    private function mainRoad(): array
    {
        return [
            'length_m' => 1000, 'width_m' => 10, 'median_width_m' => 2,
            'subbase_thickness_mm' => 150, 'subbase_unit_cost_per_m3' => 80,
            'base_course_thickness_mm' => 200, 'base_course_unit_cost_per_m3' => 120,
            'asphalt_binder_thickness_mm' => 50, 'asphalt_wearing_thickness_mm' => 40,
            'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 250,
        ];
    }

    private function crossStreet(): array
    {
        return [
            'length_m' => 200, 'width_m' => 8, 'median_width_m' => 0,
            'subbase_thickness_mm' => 100, 'subbase_unit_cost_per_m3' => 80,
            'base_course_thickness_mm' => 150, 'base_course_unit_cost_per_m3' => 120,
            'asphalt_binder_thickness_mm' => 40, 'asphalt_wearing_thickness_mm' => 30,
            'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 250,
        ];
    }

    public function test_compute_segment_main_road(): void
    {
        $result = AsphaltEstimateCalc::computeSegment($this->mainRoad());

        $this->assertSame(8.0, $result['net_width_m']);
        $this->assertSame(8000.0, $result['area_m2']);
        $this->assertSame(1200.0, $result['subbase_volume_m3']);
        $this->assertSame(1600.0, $result['base_course_volume_m3']);
        $this->assertSame(720.0, $result['asphalt_volume_m3']);
        $this->assertSame(1728.0, $result['asphalt_tons']);
        $this->assertSame(96000.0, $result['subbase_cost']);
        $this->assertSame(192000.0, $result['base_course_cost']);
        $this->assertSame(432000.0, $result['asphalt_cost']);
        $this->assertSame(720000.0, $result['segment_total']);
    }

    public function test_compute_segment_cross_street(): void
    {
        $result = AsphaltEstimateCalc::computeSegment($this->crossStreet());

        $this->assertSame(8.0, $result['net_width_m']);
        $this->assertSame(1600.0, $result['area_m2']);
        $this->assertSame(160.0, $result['subbase_volume_m3']);
        $this->assertSame(240.0, $result['base_course_volume_m3']);
        $this->assertSame(112.0, $result['asphalt_volume_m3']);
        $this->assertSame(268.8, $result['asphalt_tons']);
        $this->assertSame(12800.0, $result['subbase_cost']);
        $this->assertSame(28800.0, $result['base_course_cost']);
        $this->assertSame(67200.0, $result['asphalt_cost']);
        $this->assertSame(108800.0, $result['segment_total']);
    }

    /** A zero-thickness layer produces zero volume and zero cost for that layer — not an error, a legitimate "no such layer" input. */
    public function test_zero_thickness_layer_produces_zero_cost(): void
    {
        $segment = $this->mainRoad();
        $segment['subbase_thickness_mm'] = 0;

        $result = AsphaltEstimateCalc::computeSegment($segment);

        $this->assertSame(0.0, $result['subbase_volume_m3']);
        $this->assertSame(0.0, $result['subbase_cost']);
        // The other two layers are unaffected.
        $this->assertSame(432000.0, $result['asphalt_cost']);
    }

    /** median_width_m greater than width_m clamps net_width (and therefore area/every downstream quantity) to zero rather than going negative. */
    public function test_median_wider_than_width_clamps_net_width_to_zero(): void
    {
        $segment = $this->mainRoad();
        $segment['median_width_m'] = 999;

        $result = AsphaltEstimateCalc::computeSegment($segment);

        $this->assertSame(0.0, $result['net_width_m']);
        $this->assertSame(0.0, $result['area_m2']);
        $this->assertSame(0.0, $result['segment_total']);
    }

    public function test_compute_totals_two_segment_worked_example(): void
    {
        $computed = [
            AsphaltEstimateCalc::computeSegment($this->mainRoad()),
            AsphaltEstimateCalc::computeSegment($this->crossStreet()),
        ];
        $otherCosts = [
            'transport_cost_per_ton' => 15,
            'paver_daily_rate' => 2000, 'paver_days' => 10,
            'roller_daily_rate' => 1500, 'roller_days' => 10,
            'labor_crew_size' => 20, 'labor_days' => 10,
            'labor_daily_rate_per_worker' => 150,
            'labor_food_daily_rate_per_worker' => 40,
            'surveyor_fee' => 5000, 'lab_testing_fee' => 3000, 'mobilization_fee' => 10000,
        ];

        $totals = AsphaltEstimateCalc::computeTotals($computed, $otherCosts, 10, 15);

        $this->assertSame(828800.0, $totals['segments_subtotal']);
        $this->assertSame(1996.8, $totals['total_asphalt_tons']);
        $this->assertSame(9600.0, $totals['total_area_m2']);
        $this->assertSame(29952.0, $totals['transport_cost']);
        $this->assertSame(20000.0, $totals['paver_cost']);
        $this->assertSame(15000.0, $totals['roller_cost']);
        $this->assertSame(30000.0, $totals['labor_cost']);
        $this->assertSame(8000.0, $totals['labor_food_cost']);
        $this->assertSame(120952.0, $totals['other_costs_subtotal']);
        $this->assertSame(949752.0, $totals['subtotal']);
        $this->assertSame(94975.2, $totals['markup_amount']);
        $this->assertSame(156709.08, $totals['tax_amount']);
        $this->assertSame(1201436.28, $totals['total']);
    }
}
