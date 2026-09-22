{{-- Admin-controlled color theme (Settings → Theme). Overrides the CSS defaults in app.css so the whole
     system — public site, company panel, admin panel, client portal — recolors from one place. --}}
@php
  $themeColors = [];
  foreach (\App\Http\Controllers\Admin\SiteSettingsController::THEME_DEFAULTS as $themeKey => $themeDefault) {
      $themeValue = \App\Models\Setting::get($themeKey, '');
      $themeColors[$themeKey] = $themeValue !== '' ? $themeValue : $themeDefault;
  }
@endphp
<style>
  :root {
    --brand: {{ $themeColors['theme_brand'] }};
    --brand-dark: {{ $themeColors['theme_brand_dark'] }};
    --brand-light: {{ $themeColors['theme_brand_light'] }};
    --accent: {{ $themeColors['theme_accent'] }};
    --accent-dark: {{ $themeColors['theme_accent_dark'] }};
  }
</style>
