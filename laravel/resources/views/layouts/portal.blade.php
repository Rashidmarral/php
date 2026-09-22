<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ isset($pageTitle) ? $pageTitle . ' · ' : '' }}Client Portal</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
@include('partials.theme-vars')
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="{{ url('/portal') }}" class="logo"><span class="mark">BX</span> {{ $company->name ?? 'Client Portal' }}</a>
    <nav class="nav-links">
      <a href="{{ url('/portal') }}">Dashboard</a>
      <a href="{{ url('/portal/support') }}">Support</a>
    </nav>
    <div class="header-actions">
      <a class="lang-switch" href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}</a>
      <span class="help-text">{{ $client->name ?? '' }}</span>
      <form method="post" action="{{ url('/portal/logout') }}" style="margin:0">
        @csrf
        <button type="submit" class="btn btn-light btn-sm">Log out</button>
      </form>
    </div>
    <button type="button" class="nav-burger" id="nav-burger" aria-label="Menu" aria-expanded="false" aria-controls="mobile-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="mobile-nav" id="mobile-nav">
    <nav class="mobile-nav-links">
      <a href="{{ url('/portal') }}">Dashboard</a>
      <a href="{{ url('/portal/support') }}">Support</a>
    </nav>
    <div class="mobile-nav-actions">
      <a class="lang-switch" href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ app()->getLocale() === 'ar' ? 'العربية' : 'English' }}</a>
      <span class="help-text">{{ $client->name ?? '' }}</span>
      <form method="post" action="{{ url('/portal/logout') }}" style="margin:0">
        @csrf
        <button type="submit" class="btn btn-light btn-block">Log out</button>
      </form>
    </div>
  </div>
</header>
<div class="container" style="padding:32px 24px;">
  @if (session('flash.error'))
    @foreach ((array) session('flash.error') as $m)
      <div class="alert alert-error">{{ $m }}</div>
    @endforeach
  @endif
  @if (session('flash.success'))
    @foreach ((array) session('flash.success') as $m)
      <div class="alert alert-success">{{ $m }}</div>
    @endforeach
  @endif
  <?php session()->forget(['flash.error', 'flash.success']); ?>
  @yield('content')
</div>
<script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
<script src="{{ asset('assets/js/site.js') }}" defer></script>
</body>
</html>
