<?php

namespace Tests\Unit;

use App\Support\AsphaltSitePlanLayout;
use PHPUnit\Framework\TestCase;

/**
 * Pure-math coverage for AsphaltSitePlanLayout — no DB, no HTTP (same style
 * as AsphaltEstimateCalcTest/CriticalPathCalculatorTest). Hand-worked
 * three-segment example below.
 *
 * Canvas: CANVAS_WIDTH_PX=1000, CANVAS_HEIGHT_PX=1400, MARGIN_PX=60,
 * TITLEBLOCK_HEIGHT_PX=120.
 *   usable_width_px  = 1000 - 2*60             = 880
 *   usable_height_px = 1400 - 2*60 - 120        = 1160
 *
 * Segments: "Main Road" (is_main_road=true) length=100m width=10m
 * median=0; "Cross Street 1" length=40m width=8m; "Cross Street 2"
 * length=60m width=8m.
 *
 * Step 1: main = Main Road (flagged). Others = [Cross Street 1 (40,8),
 * Cross Street 2 (60,8)].
 *
 * Step 2: scale_h = 1160 / 100 = 11.6. widest_cross_m = max(40, 60) = 60.
 * scale_w = 880 / 60 = 14.666...7. scale = min(11.6, 14.6667) = 11.6.
 *
 * Step 3 (spine): spine_width_px = max(10*11.6, 8) = 116.0.
 * spine_height_px = 100*11.6 = 1160.0. spine_x = 60 + (880-116)/2
 * = 60 + 382 = 442.0. spine_y = 60. No median (0).
 *
 * Step 4 (cross segments), N=2:
 *   i=0 "Cross Street 1" (length=40, width=8):
 *     cross_center_y = 60 + 1160 * 1/3 = 60 + 386.6666...7 = 446.6666...7
 *     cross_length_px = min(40*11.6, 880) = 464.0
 *     cross_width_px = max(8*11.6, 6) = 92.8
 *     cross_x = 442 + 116/2 - 464/2 = 442 + 58 - 232 = 268.0
 *     cross_y = 446.6666...7 - 92.8/2 = 446.6666...7 - 46.4 = 400.2666...7
 *   i=1 "Cross Street 2" (length=60, width=8):
 *     cross_center_y = 60 + 1160 * 2/3 = 60 + 773.3333...3 = 833.3333...3
 *     cross_length_px = min(60*11.6, 880) = 696.0
 *     cross_x = 442 + 58 - 696/2 = 442 + 58 - 348 = 152.0
 *     cross_width_px = 92.8 (same as above)
 *     cross_y = 833.3333...3 - 46.4 = 786.9333...3
 *
 * Step 5 (plot grid), when Cross Street 1 (i=0) carries plot_rows=2,
 * plot_cols=3: prev_boundary_y = spine_y = 60 (i=0), band height =
 * 446.6666...7 - 60 = 386.6666...7 (> 0). Left band width = spine_x -
 * MARGIN_PX = 442 - 60 = 382 (> 0). Right band width = (60+880) -
 * (442+116) = 940 - 558 = 382 (> 0). All positive, so the grid is emitted:
 * plot_rows * plot_cols * 2 = 2*3*2 = 12 rectangles, none degenerate.
 */
class AsphaltSitePlanLayoutTest extends TestCase
{
    private function threeSegments(): array
    {
        return [
            ['name' => 'Main Road', 'length_m' => 100, 'width_m' => 10, 'median_width_m' => 0, 'is_main_road' => true, 'plot_rows' => 0, 'plot_cols' => 0],
            ['name' => 'Cross Street 1', 'length_m' => 40, 'width_m' => 8, 'median_width_m' => 0, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0],
            ['name' => 'Cross Street 2', 'length_m' => 60, 'width_m' => 8, 'median_width_m' => 0, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0],
        ];
    }

    public function test_hand_worked_example_matches_exactly(): void
    {
        $layout = AsphaltSitePlanLayout::compute($this->threeSegments());

        $this->assertTrue($layout['hasLayout']);
        $this->assertEqualsWithDelta(11.6, $layout['scale'], 0.0000001);

        $spine = $layout['spine'];
        $this->assertEqualsWithDelta(442.0, $spine['x'], 0.0000001);
        $this->assertEqualsWithDelta(60.0, $spine['y'], 0.0000001);
        $this->assertEqualsWithDelta(116.0, $spine['width'], 0.0000001);
        $this->assertEqualsWithDelta(1160.0, $spine['height'], 0.0000001);
        $this->assertNull($spine['medianX']);
        $this->assertNull($spine['medianWidth']);

        $cross = $layout['crossSegments'];
        $this->assertCount(2, $cross);

        $this->assertEqualsWithDelta(268.0, $cross[0]['x'], 0.0000001);
        $this->assertEqualsWithDelta(400.26666666666667, $cross[0]['y'], 0.0000001);
        $this->assertEqualsWithDelta(464.0, $cross[0]['width'], 0.0000001);
        $this->assertEqualsWithDelta(92.8, $cross[0]['height'], 0.0000001);

        $this->assertEqualsWithDelta(152.0, $cross[1]['x'], 0.0000001);
        $this->assertEqualsWithDelta(786.9333333333334, $cross[1]['y'], 0.0000001);
        $this->assertEqualsWithDelta(696.0, $cross[1]['width'], 0.0000001);
        $this->assertEqualsWithDelta(92.8, $cross[1]['height'], 0.0000001);
    }

    public function test_zero_segments_returns_no_layout(): void
    {
        $layout = AsphaltSitePlanLayout::compute([]);

        $this->assertSame(['hasLayout' => false], $layout);
    }

    /** No plot_rows/plot_cols anywhere => zero plot-grid rectangles. */
    public function test_no_plot_grid_when_not_requested(): void
    {
        $layout = AsphaltSitePlanLayout::compute($this->threeSegments());

        $this->assertSame([], $layout['plotGrids']);
    }

    /** See this class's own docblock: plot_rows=2, plot_cols=3 on the first cross segment => exactly 12 rectangles, none degenerate. */
    public function test_plot_grid_produces_exact_rectangle_count_with_no_negative_sizes(): void
    {
        $segments = $this->threeSegments();
        $segments[1]['plot_rows'] = 2;
        $segments[1]['plot_cols'] = 3;

        $layout = AsphaltSitePlanLayout::compute($segments);

        $this->assertCount(12, $layout['plotGrids']);
        foreach ($layout['plotGrids'] as $rect) {
            $this->assertGreaterThan(0, $rect['width']);
            $this->assertGreaterThan(0, $rect['height']);
        }
    }

    /** No segment flagged is_main_road => falls back to the longest segment (ties go to the lowest array index). */
    public function test_falls_back_to_longest_segment_when_none_flagged(): void
    {
        $segments = [
            ['name' => 'Short', 'length_m' => 40, 'width_m' => 8, 'median_width_m' => 0, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0],
            ['name' => 'Longest', 'length_m' => 100, 'width_m' => 10, 'median_width_m' => 0, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0],
            ['name' => 'Tied', 'length_m' => 100, 'width_m' => 10, 'median_width_m' => 0, 'is_main_road' => false, 'plot_rows' => 0, 'plot_cols' => 0],
        ];

        $layout = AsphaltSitePlanLayout::compute($segments);

        // "Longest" (index 1) wins the length tie against "Tied" (index 2)
        // because it comes first.
        $this->assertSame('Longest', $layout['spine']['name']);
    }

    /** A single segment (no cross streets at all) still produces a valid spine, scaled against its own width. */
    public function test_single_segment_has_no_cross_segments(): void
    {
        $layout = AsphaltSitePlanLayout::compute([
            ['name' => 'Solo Road', 'length_m' => 50, 'width_m' => 12, 'median_width_m' => 0, 'is_main_road' => true, 'plot_rows' => 0, 'plot_cols' => 0],
        ]);

        $this->assertTrue($layout['hasLayout']);
        $this->assertSame([], $layout['crossSegments']);
        $this->assertSame([], $layout['plotGrids']);
    }

    /** median_width_m > 0 produces a centered median strip inside the spine. */
    public function test_spine_median_strip_is_centered(): void
    {
        $layout = AsphaltSitePlanLayout::compute([
            ['name' => 'Main Road', 'length_m' => 100, 'width_m' => 10, 'median_width_m' => 2, 'is_main_road' => true, 'plot_rows' => 0, 'plot_cols' => 0],
        ]);

        $spine = $layout['spine'];
        // scale = min(1160/100, 880/10) = min(11.6, 88) = 11.6 (no cross segments => widest_cross_m = main.width_m = 10).
        $this->assertEqualsWithDelta(11.6, $layout['scale'], 0.0000001);
        $medianWidthPx = 2 * 11.6; // 23.2
        $this->assertEqualsWithDelta($medianWidthPx, $spine['medianWidth'], 0.0000001);
        $expectedMedianX = $spine['x'] + ($spine['width'] - $medianWidthPx) / 2;
        $this->assertEqualsWithDelta($expectedMedianX, $spine['medianX'], 0.0000001);
    }

    /** An optional site boundary box is only returned when both dimensions are given, and is centered on the spine. */
    public function test_site_boundary_only_drawn_when_both_dimensions_given(): void
    {
        $segments = $this->threeSegments();

        $this->assertNull(AsphaltSitePlanLayout::compute($segments, null, null)['siteBoundary']);
        $this->assertNull(AsphaltSitePlanLayout::compute($segments, 200.0, null)['siteBoundary']);

        $layout = AsphaltSitePlanLayout::compute($segments, 200.0, 150.0);
        $boundary = $layout['siteBoundary'];
        $this->assertNotNull($boundary);
        $this->assertEqualsWithDelta(200.0 * 11.6, $boundary['width'], 0.0000001);
        $this->assertEqualsWithDelta(150.0 * 11.6, $boundary['height'], 0.0000001);
    }
}
