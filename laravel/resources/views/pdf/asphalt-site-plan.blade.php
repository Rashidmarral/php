<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<title>{{ $estimate['name'] }} — {{ t('user.asphalt_estimates.drawing_heading') }}</title>
<style>
  {{--
    The drawing is a fixed 1000x1400 CSS-px canvas (AsphaltSitePlanLayout's
    own constants). A4/letter would clip or paginate it, so the page size is
    set to match the canvas exactly, at the same 96dpi px->pt conversion
    dompdf itself uses (1px = 72/96pt): 1000px = 750pt, 1400px = 1050pt.
  --}}
  @page { margin: 0; size: 750pt 1060pt; }
  html, body { margin: 0; padding: 0; font-family: Cairo, sans-serif; }
</style>
</head>
<body>
{{-- The exact same partial the live preview (show.blade.php) includes —
     there is only one rendering source for the drawing, never two copies
     of the drawing markup. --}}
@include('app.asphalt-estimates._site-plan', ['layout' => $layout, 'estimate' => $estimate])
</body>
</html>
