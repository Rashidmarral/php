<?php
use App\Models\Page;
use App\Models\Setting;

$isAr = app()->getLocale() === 'ar';
$otherLang = $isAr ? 'en' : 'ar';
$otherLangLabel = $isAr ? 'EN' : 'AR';
$platformLogo = Setting::get('platform_logo_path', '');
$platformCr = Setting::get('platform_cr_number', '');
$platformVat = Setting::get('platform_vat_number', '');
$platformLegalName = $isAr
    ? (Setting::get('platform_legal_name_ar', '') ?: Setting::get('platform_legal_name_en', 'BuildXact Saudi'))
    : Setting::get('platform_legal_name_en', 'BuildXact Saudi');
$headerPhone = Setting::get('header_phone', '');
$footerTagline = ($isAr ? Setting::get('footer_tagline_ar', '') : Setting::get('footer_tagline_en', '')) ?: t('footer.tagline');
$footerCities = ($isAr ? Setting::get('footer_cities_ar', '') : Setting::get('footer_cities_en', '')) ?: 'Riyadh · Jeddah · Dammam';
$footerBottomNote = $isAr ? Setting::get('footer_bottom_note_ar', '') : Setting::get('footer_bottom_note_en', '');
$socialLinks = [
    'Facebook' => Setting::get('social_facebook_url', ''),
    'X' => Setting::get('social_twitter_url', ''),
    'Instagram' => Setting::get('social_instagram_url', ''),
    'LinkedIn' => Setting::get('social_linkedin_url', ''),
    'WhatsApp' => Setting::get('social_whatsapp_url', ''),
];
$navPages = [];
$footerPages = [];
try {
    $navPages = Page::forNav();
    $footerPages = Page::forFooter();
} catch (\Throwable $e) {
    // pages table may not exist yet on a not-yet-migrated install
}
?><!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ $isAr ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($pageTitle) ? $pageTitle . ' · ' : '' }}BuildXact Saudi</title>
<meta name="description" content="Construction management and job costing software for Saudi Arabia's contractors, builders and developers.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
<script>document.documentElement.classList.add('js');</script>
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="{{ url('/') }}" class="logo">
      @if($platformLogo)
        <img src="{{ $platformLogo }}" alt="Logo" style="height:32px;border-radius:6px;">
      @else
        <span class="mark">BX</span>
      @endif
      BuildXact <span style="color:#d4a017">السعودية</span>
    </a>
    <nav class="nav-links">
      <a href="{{ url('/features') }}">{{ t('nav.features') }}</a>
      <a href="{{ url('/pricing') }}">{{ t('nav.pricing') }}</a>
      <a href="{{ url('/quick-estimate') }}">{{ t('nav.quick_estimate') }}</a>
      <a href="{{ url('/about') }}">{{ t('nav.about') }}</a>
      <a href="{{ url('/contact') }}">{{ t('nav.contact') }}</a>
      @foreach ($navPages as $np)
        <a href="{{ url('/p/' . $np->slug) }}">{{ $isAr ? ($np->nav_label_ar ?: $np->title_ar) : ($np->nav_label_en ?: $np->title_en) }}</a>
      @endforeach
    </nav>
    <div class="header-actions">
      @if($headerPhone)
        <a class="lang-switch" href="tel:{{ preg_replace('/\s+/', '', $headerPhone) }}" style="direction:ltr;">{{ $headerPhone }}</a>
      @endif
      <a class="lang-switch" href="?lang={{ $otherLang }}">{{ $otherLangLabel }}</a>
      @auth
        <a class="btn btn-outline btn-sm" href="{{ auth()->user()->isSuperAdmin() ? url('/admin') : url('/app') }}">{{ t('nav.dashboard') }}</a>
      @else
        <a class="btn btn-light btn-sm" href="{{ url('/login') }}">{{ t('nav.login') }}</a>
        <a class="btn btn-primary btn-sm" href="{{ url('/register') }}">{{ t('nav.start_trial') }}</a>
      @endauth
    </div>
  </div>
</header>

@yield('content')

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="logo" style="color:#fff"><span class="mark">BX</span> BuildXact Saudi</div>
        <p style="color:#a9c4bd;font-size:13.5px;margin-top:10px;max-width:280px;">{{ $footerTagline }}</p>
        @if(array_filter($socialLinks))
          <div style="display:flex;gap:10px;margin-top:14px;">
            @foreach ($socialLinks as $label => $url)
              @continue(!$url)
              <a href="{{ $url }}" target="_blank" rel="noopener" title="{{ $label }}" style="color:#a9c4bd;font-size:12.5px;border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:4px 8px;">{{ $label }}</a>
            @endforeach
          </div>
        @endif
      </div>
      <div>
        <h4>{{ t('footer.product') }}</h4>
        <ul>
          <li><a href="{{ url('/features') }}">{{ t('nav.features') }}</a></li>
          <li><a href="{{ url('/pricing') }}">{{ t('nav.pricing') }}</a></li>
          <li><a href="{{ url('/login') }}">{{ t('nav.login') }}</a></li>
        </ul>
      </div>
      <div>
        <h4>{{ t('footer.company') }}</h4>
        <ul>
          <li><a href="{{ url('/about') }}">{{ t('nav.about') }}</a></li>
          <li><a href="{{ url('/contact') }}">{{ t('nav.contact') }}</a></li>
          @foreach ($footerPages as $fp)
            <li><a href="{{ url('/p/' . $fp->slug) }}">{{ $isAr ? ($fp->nav_label_ar ?: $fp->title_ar) : ($fp->nav_label_en ?: $fp->title_en) }}</a></li>
          @endforeach
        </ul>
      </div>
      <div>
        <h4>{{ t('footer.legal') }}</h4>
        <ul>
          <li><a href="{{ url('/privacy') }}">{{ t('footer.privacy') }}</a></li>
          <li><a href="{{ url('/terms') }}">{{ t('footer.terms') }}</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>
        &copy; {{ date('Y') }} {{ $platformLegalName }}. {{ t('footer.rights') }}
        @if($platformCr || $platformVat)
          @if($platformCr) · {{ t('footer.cr') }}: <bdi>{{ $platformCr }}</bdi> @endif
          @if($platformVat) · {{ t('footer.vat') }}: <bdi>{{ $platformVat }}</bdi> @endif
        @endif
        @if($footerBottomNote) · {{ $footerBottomNote }} @endif
      </span>
      <span>{{ $footerCities }}</span>
    </div>
  </div>
</footer>
<script src="{{ asset('assets/js/site.js') }}" defer></script>
</body>
</html>
