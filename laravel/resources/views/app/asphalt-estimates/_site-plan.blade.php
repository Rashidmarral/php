{{--
  Renders App\Support\AsphaltSitePlanLayout::compute()'s output as a
  dimensioned 2D line drawing: a fixed-pixel-size container with
  absolutely-positioned child <div>s, every left/top/width/height already
  computed server-side in PHP (never client-side JS — dompdf, used for the
  PDF download, does not execute JavaScript). Included identically by both
  consumers:
    - app/asphalt-estimates/show.blade.php (live on-page preview)
    - pdf/asphalt-site-plan.blade.php (the downloadable PDF)
  so there is exactly one rendering source for the drawing, never two. Takes
  $layout (with hasLayout === true — callers are responsible for showing an
  empty state / redirecting instead when it's false) and $estimate (an
  array, for the title block).
--}}
@if(! empty($layout['hasLayout']))
<?php
$spine = $layout['spine'];
$gates = $layout['gates'];
$siteBoundary = $layout['siteBoundary'] ?? null;

/** "{name}: {length}m × {width}m" (+ median note) — pure string formatting on numbers the layout already computed, not geometry math. */
$dimensionCaption = function (array $seg): string {
    $caption = e($seg['name']).': '.rtrim(rtrim(number_format($seg['lengthM'], 2), '0'), '.').'m × '.rtrim(rtrim(number_format($seg['widthM'], 2), '0'), '.').'m';
    if ($seg['medianWidthM'] > 0) {
        $caption .= ' ('.t('user.asphalt_estimates.median_note', ['width' => rtrim(rtrim(number_format($seg['medianWidthM'], 2), '0'), '.')]).')';
    }

    return $caption;
};
?>
<div style="position:relative;width:{{ $layout['canvasWidth'] }}px;height:{{ $layout['canvasHeight'] }}px;border:2px solid #000;background:#fff;font-family:Cairo, sans-serif;overflow:hidden;">

  {{-- North arrow, fixed top-right corner of the usable area --}}
  <div style="position:absolute;left:{{ $layout['canvasWidth'] - \App\Support\AsphaltSitePlanLayout::MARGIN_PX - 24 }}px;top:{{ \App\Support\AsphaltSitePlanLayout::MARGIN_PX - 10 }}px;width:48px;text-align:center;font-size:18px;line-height:1.1;">
    <div>&#8593;</div>
    <div style="font-size:10px;font-weight:bold;">{{ t('user.asphalt_estimates.north') }}</div>
  </div>

  @if($siteBoundary)
    {{-- Optional overall site boundary — purely cosmetic/informational, centered on the spine (see AsphaltSitePlanLayout's docblock). --}}
    <div style="position:absolute;left:{{ $siteBoundary['x'] }}px;top:{{ $siteBoundary['y'] }}px;width:{{ $siteBoundary['width'] }}px;height:{{ $siteBoundary['height'] }}px;border:1px dashed #999;"></div>
  @endif

  {{-- Plot grid rectangles (drawn under the roads, so the road rectangles paint on top). --}}
  @foreach($layout['plotGrids'] as $cell)
    <div style="position:absolute;left:{{ $cell['x'] }}px;top:{{ $cell['y'] }}px;width:{{ $cell['width'] }}px;height:{{ $cell['height'] }}px;border:1px solid #c9c9c9;background:rgba(0,0,0,0.02);"></div>
  @endforeach

  {{-- The spine (main road). --}}
  <div style="position:absolute;left:{{ $spine['x'] }}px;top:{{ $spine['y'] }}px;width:{{ $spine['width'] }}px;height:{{ $spine['height'] }}px;background:#4a4a4a;"></div>
  @if($spine['medianX'] !== null)
    <div style="position:absolute;left:{{ $spine['medianX'] }}px;top:{{ $spine['y'] }}px;width:{{ $spine['medianWidth'] }}px;height:{{ $spine['height'] }}px;background:#8bc34a;"></div>
  @endif
  <div style="position:absolute;left:{{ $spine['x'] + $spine['width'] + 8 }}px;top:{{ $spine['y'] + $spine['height'] / 2 - 10 }}px;width:260px;font-size:11px;white-space:nowrap;">{!! $dimensionCaption($spine) !!}</div>

  {{-- Cross segments. --}}
  @foreach($layout['crossSegments'] as $cross)
    <div style="position:absolute;left:{{ $cross['x'] }}px;top:{{ $cross['y'] }}px;width:{{ $cross['width'] }}px;height:{{ $cross['height'] }}px;background:#6b6b6b;"></div>
    @if($cross['medianX'] !== null)
      <div style="position:absolute;left:{{ $cross['medianX'] }}px;top:{{ $cross['y'] }}px;width:{{ $cross['medianWidth'] }}px;height:{{ $cross['height'] }}px;background:#8bc34a;"></div>
    @endif
    <div style="position:absolute;left:{{ $cross['x'] }}px;top:{{ $cross['y'] + $cross['height'] + 4 }}px;width:{{ $cross['width'] }}px;text-align:center;font-size:10.5px;white-space:nowrap;">{!! $dimensionCaption($cross) !!}</div>
  @endforeach

  {{-- Gate markers. --}}
  <div style="position:absolute;left:{{ $gates['entrance']['x'] - 60 }}px;top:{{ $gates['entrance']['y'] - 24 }}px;width:120px;text-align:center;font-size:10.5px;font-weight:bold;">&#9650; {{ t('user.asphalt_estimates.entrance_gate') }}</div>
  <div style="position:absolute;left:{{ $gates['exit']['x'] - 60 }}px;top:{{ $gates['exit']['y'] + 6 }}px;width:120px;text-align:center;font-size:10.5px;font-weight:bold;">{{ t('user.asphalt_estimates.exit_gate') }} &#9660;</div>

  {{-- Title block. --}}
  <div style="position:absolute;left:0;top:{{ $layout['canvasHeight'] - \App\Support\AsphaltSitePlanLayout::TITLEBLOCK_HEIGHT_PX }}px;width:100%;height:{{ \App\Support\AsphaltSitePlanLayout::TITLEBLOCK_HEIGHT_PX }}px;border-top:2px solid #000;box-sizing:border-box;padding:10px 16px;font-size:11px;">
    <div style="font-size:14px;font-weight:bold;margin-bottom:4px;">{{ t('user.asphalt_estimates.drawing_heading') }} — {{ $estimate['name'] }}</div>
    <div>{{ t('user.asphalt_estimates.area_tons_reference', ['area' => number_format((float) $estimate['total_area_m2'], 2), 'tons' => number_format((float) $estimate['total_asphalt_tons'], 2)]) }}</div>
    <div>{{ \Carbon\Carbon::now()->format('Y-m-d') }}</div>
    <div style="margin-top:4px;font-style:italic;color:#555;">{{ t('user.asphalt_estimates.drawing_disclaimer') }}</div>
  </div>
</div>
@endif
