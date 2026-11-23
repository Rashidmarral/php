<?php

namespace App\Support;

/**
 * Pure 2D layout math for the Asphalt Estimate "site plan" drawing — no DB,
 * no HTTP (same style as CriticalPathCalculator/AsphaltEstimateCalc). Turns
 * an estimate's road segments into plain pixel coordinates for a fixed-size
 * canvas; both the live on-page preview
 * (resources/views/app/asphalt-estimates/_site-plan.blade.php, included
 * from show.blade.php) and the downloadable PDF
 * (resources/views/pdf/asphalt-site-plan.blade.php, which just includes that
 * same partial) render the exact same compute() output — there is only one
 * geometry computation, never duplicated between the two render paths.
 *
 * This is a dimensioned line drawing, not a survey/CAD tool: every number
 * below is a deliberately simple geometric approximation (segments as
 * rectangles, cross segments evenly spaced down the main segment's length)
 * good enough to give a contractor a readable, labeled sketch of their road
 * network — not a substitute for real civil/survey drawings.
 *
 * Canvas (fixed, in CSS pixels):
 *   CANVAS_WIDTH_PX = 1000, CANVAS_HEIGHT_PX = 1400, MARGIN_PX = 60,
 *   TITLEBLOCK_HEIGHT_PX = 120 (reserved at the bottom for the title block).
 *   usable_width_px  = CANVAS_WIDTH_PX - 2*MARGIN_PX                         = 880
 *   usable_height_px = CANVAS_HEIGHT_PX - 2*MARGIN_PX - TITLEBLOCK_HEIGHT_PX = 1160
 *
 * Step 1 — pick the main segment: the one with is_main_road === true; if
 * none is flagged, the segment with the greatest length_m (ties broken by
 * the lowest array index, i.e. the earliest-entered segment wins). Zero
 * segments total => hasLayout = false and nothing else is computed.
 *
 * Step 2 — scale (px per meter), chosen so the whole drawing fits the
 * canvas:
 *   scale_h = usable_height_px / main.length_m
 *   widest_cross_m = max(length_m over every OTHER segment), or
 *                    main.width_m when there are no other segments
 *   scale_w = usable_width_px / widest_cross_m
 *   scale = min(scale_h, scale_w)
 *
 * Step 3 — the spine (the main segment, drawn vertically, horizontally
 * centered in the usable area):
 *   spine_width_px  = max(main.width_m * scale, 8)   // 8px floor: stays visible even for a narrow road
 *   spine_height_px = main.length_m * scale
 *   spine_x = MARGIN_PX + (usable_width_px - spine_width_px) / 2
 *   spine_y = MARGIN_PX
 *   If main.median_width_m > 0, a centered median strip inside the spine:
 *   median_width_px = main.median_width_m * scale,
 *   median_x = spine_x + (spine_width_px - median_width_px) / 2, same y/height as the spine.
 *
 * Step 4 — cross segments (every other segment, in their existing
 * sort_order/array order). With N of them, the i-th one (0-indexed) is
 * centered ON the spine at:
 *   cross_center_y  = spine_y + spine_height_px * (i + 1) / (N + 1)
 *   cross_length_px = min(segment.length_m * scale, usable_width_px)
 *   cross_width_px  = max(segment.width_m * scale, 6)
 *   cross_x = spine_x + spine_width_px / 2 - cross_length_px / 2
 *   cross_y = cross_center_y - cross_width_px / 2
 *   Same median-strip treatment as the spine, centered within the cross
 *   segment's own band, if segment.median_width_m > 0.
 *
 * Step 5 — plot grids: only for a CROSS segment (never the main segment
 * itself — there is no "previous cross street" to anchor its vertical band
 * against, so main-segment grids are deliberately skipped) with
 * plot_rows > 0 AND plot_cols > 0. For the i-th cross segment, the vertical
 * band is [prev_boundary_y, cross_center_y) where prev_boundary_y is
 * spine_y for i = 0, or the (i-1)-th cross segment's own cross_center_y
 * otherwise. The horizontal bands are the margin strips either side of the
 * spine: left = [MARGIN_PX, spine_x], right = [spine_x + spine_width_px,
 * MARGIN_PX + usable_width_px]. The vertical band is split into plot_rows
 * equal rows, each horizontal band into plot_cols equal columns, emitting
 * one rectangle per cell on BOTH sides (plot_rows * plot_cols * 2 rectangles
 * total for that segment). If the band height or either band's width would
 * be <= 0 (cross segments packed too close together, or the margin itself
 * is too narrow), that segment's entire grid is skipped rather than
 * emitting degenerate/negative-size rectangles.
 *
 * Step 6 — gate markers: an "Entrance Gate" point centered horizontally at
 * spine_x + spine_width_px / 2, y = spine_y - 15 (just above the spine's
 * top), and an "Exit Gate" point at the same x, y = spine_y +
 * spine_height_px + 5 (just below the spine's bottom). Labels/translation
 * are entirely the view's concern — this class only returns coordinates.
 *
 * Optional site boundary box: purely cosmetic/informational, drawn only
 * when BOTH site_width_m and site_length_m are given. It is centered on the
 * spine both horizontally and vertically (an arbitrary but documented
 * choice, since there is no further requirement on where an informational
 * "site boundary" sits relative to the roads inside it):
 *   boundary_width_px  = site_width_m * scale
 *   boundary_height_px = site_length_m * scale
 *   boundary_x = spine_x + spine_width_px / 2 - boundary_width_px / 2
 *   boundary_y = (spine_y + spine_height_px / 2) - boundary_height_px / 2
 */
class AsphaltSitePlanLayout
{
    public const CANVAS_WIDTH_PX = 1000;

    public const CANVAS_HEIGHT_PX = 1400;

    public const MARGIN_PX = 60;

    public const TITLEBLOCK_HEIGHT_PX = 120;

    /**
     * @param  array<int, array{name: string, length_m: float, width_m: float, median_width_m: float, is_main_road: bool, plot_rows: int, plot_cols: int}>  $segments  in sort_order
     * @return array{
     *     hasLayout: bool,
     *     canvasWidth?: int,
     *     canvasHeight?: int,
     *     scale?: float,
     *     spine?: array{x: float, y: float, width: float, height: float, medianX: ?float, medianWidth: ?float, name: string, lengthM: float, widthM: float, medianWidthM: float},
     *     crossSegments?: array<int, array{x: float, y: float, width: float, height: float, medianX: ?float, medianWidth: ?float, name: string, lengthM: float, widthM: float, medianWidthM: float}>,
     *     plotGrids?: array<int, array{x: float, y: float, width: float, height: float}>,
     *     gates?: array{entrance: array{x: float, y: float}, exit: array{x: float, y: float}},
     *     siteBoundary?: ?array{x: float, y: float, width: float, height: float},
     * }
     */
    public static function compute(array $segments, ?float $siteWidthM = null, ?float $siteLengthM = null): array
    {
        if (empty($segments)) {
            return ['hasLayout' => false];
        }

        $usableWidthPx = self::CANVAS_WIDTH_PX - 2 * self::MARGIN_PX;
        $usableHeightPx = self::CANVAS_HEIGHT_PX - 2 * self::MARGIN_PX - self::TITLEBLOCK_HEIGHT_PX;

        [$mainIndex, $main] = self::pickMainSegment($segments);
        $others = array_values(array_filter($segments, fn ($s, $i) => $i !== $mainIndex, ARRAY_FILTER_USE_BOTH));

        $mainLengthM = (float) $main['length_m'];
        $widestCrossM = empty($others) ? (float) $main['width_m'] : max(array_map(fn ($s) => (float) $s['length_m'], $others));
        $widestCrossM = $widestCrossM > 0 ? $widestCrossM : 0.01;
        $mainLengthM = $mainLengthM > 0 ? $mainLengthM : 0.01;

        $scaleH = $usableHeightPx / $mainLengthM;
        $scaleW = $usableWidthPx / $widestCrossM;
        $scale = min($scaleH, $scaleW);

        $spineWidthPx = max((float) $main['width_m'] * $scale, 8.0);
        $spineHeightPx = (float) $main['length_m'] * $scale;
        $spineX = self::MARGIN_PX + ($usableWidthPx - $spineWidthPx) / 2;
        $spineY = (float) self::MARGIN_PX;

        [$spineMedianX, $spineMedianWidth] = self::medianStrip((float) $main['median_width_m'], $scale, $spineX, $spineWidthPx);

        $spine = [
            'x' => $spineX,
            'y' => $spineY,
            'width' => $spineWidthPx,
            'height' => $spineHeightPx,
            'medianX' => $spineMedianX,
            'medianWidth' => $spineMedianWidth,
            'name' => (string) $main['name'],
            'lengthM' => (float) $main['length_m'],
            'widthM' => (float) $main['width_m'],
            'medianWidthM' => (float) $main['median_width_m'],
        ];

        $n = count($others);
        $crossSegments = [];
        $crossCenterYs = [];
        foreach ($others as $i => $segment) {
            $crossCenterY = $spineY + $spineHeightPx * ($i + 1) / ($n + 1);
            $crossCenterYs[] = $crossCenterY;
            $crossLengthPx = min((float) $segment['length_m'] * $scale, $usableWidthPx);
            $crossWidthPx = max((float) $segment['width_m'] * $scale, 6.0);
            $crossX = $spineX + $spineWidthPx / 2 - $crossLengthPx / 2;
            $crossY = $crossCenterY - $crossWidthPx / 2;

            [$medianX, $medianWidth] = self::medianStrip((float) $segment['median_width_m'], $scale, $crossX, $crossLengthPx);

            $crossSegments[] = [
                'x' => $crossX,
                'y' => $crossY,
                'width' => $crossLengthPx,
                'height' => $crossWidthPx,
                'medianX' => $medianX,
                'medianWidth' => $medianWidth,
                'name' => (string) $segment['name'],
                'lengthM' => (float) $segment['length_m'],
                'widthM' => (float) $segment['width_m'],
                'medianWidthM' => (float) $segment['median_width_m'],
            ];
        }

        $plotGrids = [];
        $leftBandX = (float) self::MARGIN_PX;
        $leftBandWidth = $spineX - self::MARGIN_PX;
        $rightBandX = $spineX + $spineWidthPx;
        $rightBandWidth = (self::MARGIN_PX + $usableWidthPx) - $rightBandX;
        $prevBoundaryY = $spineY;
        foreach ($others as $i => $segment) {
            $bandY = $prevBoundaryY;
            $bandHeight = $crossCenterYs[$i] - $prevBoundaryY;
            $prevBoundaryY = $crossCenterYs[$i];

            $plotRows = (int) $segment['plot_rows'];
            $plotCols = (int) $segment['plot_cols'];
            if ($plotRows <= 0 || $plotCols <= 0) {
                continue;
            }
            if ($bandHeight <= 0 || $leftBandWidth <= 0 || $rightBandWidth <= 0) {
                continue;
            }

            $rowHeight = $bandHeight / $plotRows;
            $leftColWidth = $leftBandWidth / $plotCols;
            $rightColWidth = $rightBandWidth / $plotCols;
            for ($r = 0; $r < $plotRows; $r++) {
                $y = $bandY + $r * $rowHeight;
                for ($c = 0; $c < $plotCols; $c++) {
                    $plotGrids[] = ['x' => $leftBandX + $c * $leftColWidth, 'y' => $y, 'width' => $leftColWidth, 'height' => $rowHeight];
                    $plotGrids[] = ['x' => $rightBandX + $c * $rightColWidth, 'y' => $y, 'width' => $rightColWidth, 'height' => $rowHeight];
                }
            }
        }

        $gateX = $spineX + $spineWidthPx / 2;
        $gates = [
            'entrance' => ['x' => $gateX, 'y' => $spineY - 15],
            'exit' => ['x' => $gateX, 'y' => $spineY + $spineHeightPx + 5],
        ];

        $siteBoundary = null;
        if ($siteWidthM !== null && $siteLengthM !== null && $siteWidthM > 0 && $siteLengthM > 0) {
            $boundaryWidthPx = $siteWidthM * $scale;
            $boundaryHeightPx = $siteLengthM * $scale;
            $siteBoundary = [
                'x' => $spineX + $spineWidthPx / 2 - $boundaryWidthPx / 2,
                'y' => ($spineY + $spineHeightPx / 2) - $boundaryHeightPx / 2,
                'width' => $boundaryWidthPx,
                'height' => $boundaryHeightPx,
            ];
        }

        return [
            'hasLayout' => true,
            'canvasWidth' => self::CANVAS_WIDTH_PX,
            'canvasHeight' => self::CANVAS_HEIGHT_PX,
            'scale' => $scale,
            'spine' => $spine,
            'crossSegments' => $crossSegments,
            'plotGrids' => $plotGrids,
            'gates' => $gates,
            'siteBoundary' => $siteBoundary,
        ];
    }

    /**
     * @param  array<int, array{median_width_m: float}>  $medianWidthM
     * @return array{0: ?float, 1: ?float} [medianX, medianWidth] — both null when there is no median.
     */
    private static function medianStrip(float $medianWidthM, float $scale, float $bandX, float $bandWidthPx): array
    {
        if ($medianWidthM <= 0) {
            return [null, null];
        }
        $medianWidthPx = $medianWidthM * $scale;
        $medianX = $bandX + ($bandWidthPx - $medianWidthPx) / 2;

        return [$medianX, $medianWidthPx];
    }

    /**
     * @param  array<int, array{is_main_road: bool, length_m: float}>  $segments
     * @return array{0: int, 1: array{is_main_road: bool, length_m: float}} [index, segment]
     */
    private static function pickMainSegment(array $segments): array
    {
        foreach ($segments as $i => $segment) {
            if (! empty($segment['is_main_road'])) {
                return [$i, $segment];
            }
        }

        $bestIndex = 0;
        $bestLength = -INF;
        foreach ($segments as $i => $segment) {
            $length = (float) $segment['length_m'];
            if ($length > $bestLength) {
                $bestLength = $length;
                $bestIndex = $i;
            }
        }

        return [$bestIndex, $segments[$bestIndex]];
    }
}
