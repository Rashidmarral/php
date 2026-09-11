<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@hasSection('title')@yield('title') · @endif {{ \App\Models\Setting::siteName() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
<link rel="manifest" href="{{ asset('manifest.json') }}">
<meta name="theme-color" content="#16233f">
<link rel="apple-touch-icon" href="{{ asset('assets/icons/icon-192.png') }}">
@include('partials.theme-vars')
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="mark" style="background:#fff;color:var(--brand-dark)">BX</span> {{ \App\Models\Setting::siteName() }}</div>
    <nav>
      <a href="{{ url('/app') }}" class="{{ request()->is('app') ? 'active' : '' }}">📊 {{ t('side.dashboard') }}</a>
      <a href="{{ url('/app/projects') }}" class="{{ request()->is('app/projects*') ? 'active' : '' }}">🏗️ {{ t('side.projects') }}</a>
      <a href="{{ url('/app/quick-estimate') }}" class="{{ request()->is('app/quick-estimate*') ? 'active' : '' }}">⚡ {{ t('side.quick_estimate') }}</a>
      <a href="{{ url('/app/estimates') }}" class="{{ request()->is('app/estimates*') ? 'active' : '' }}">🧾 {{ t('side.estimates') }}</a>
      <a href="{{ url('/app/invoices') }}" class="{{ request()->is('app/invoices*') ? 'active' : '' }}">💳 {{ t('side.invoices') }}</a>
      <a href="{{ url('/app/credit-notes') }}" class="{{ request()->is('app/credit-notes*') ? 'active' : '' }}">↩️ {{ t('side.credit_notes') }}</a>
      <a href="{{ url('/app/debit-notes') }}" class="{{ request()->is('app/debit-notes*') ? 'active' : '' }}">➕ {{ t('side.debit_notes') }}</a>
      <a href="{{ url('/app/clients') }}" class="{{ request()->is('app/clients*') ? 'active' : '' }}">👥 {{ t('side.clients') }}</a>
      <a href="{{ url('/app/schedule') }}" class="{{ request()->is('app/schedule*') ? 'active' : '' }}">📅 {{ t('side.schedule') }}</a>
      <a href="{{ url('/app/takeoffs') }}" class="{{ request()->is('app/takeoffs*') ? 'active' : '' }}">📐 {{ t('side.takeoffs') }}</a>

      <div class="nav-section">{{ t('side.section_resources') }}</div>
      <a href="{{ url('/app/suppliers') }}" class="{{ request()->is('app/suppliers*') ? 'active' : '' }}">🚚 {{ t('side.suppliers') }}</a>
      <a href="{{ url('/app/materials') }}" class="{{ request()->is('app/materials*') ? 'active' : '' }}">📦 {{ t('side.materials') }}</a>
      <a href="{{ url('/app/documents') }}" class="{{ request()->is('app/documents*') ? 'active' : '' }}">📁 {{ t('side.documents') }}</a>

      <div class="nav-section">{{ t('side.section_insights') }}</div>
      <a href="{{ url('/app/reports') }}" class="{{ request()->is('app/reports*') ? 'active' : '' }}">📈 {{ t('side.reports') }}</a>
      <a href="{{ url('/app/consultations') }}" class="{{ request()->is('app/consultations*') ? 'active' : '' }}">🎓 {{ t('side.consultations') }}</a>
      <a href="{{ url('/app/support') }}" class="{{ request()->is('app/support*') ? 'active' : '' }}">🎧 {{ t('side.support') }}</a>

      <div class="nav-section">{{ t('side.section_company') }}</div>
      <a href="{{ url('/app/leads') }}" class="{{ request()->is('app/leads*') ? 'active' : '' }}">🎯 {{ t('side.leads') }}</a>
      <a href="{{ url('/app/tenders') }}" class="{{ request()->is('app/tenders*') ? 'active' : '' }}">📋 {{ t('side.tenders') }}</a>
      <a href="{{ url('/app/team') }}" class="{{ request()->is('app/team*') ? 'active' : '' }}">🧑‍💼 {{ t('side.team') }}</a>
      <a href="{{ url('/app/billing') }}" class="{{ request()->is('app/billing*') ? 'active' : '' }}">💰 {{ t('side.billing') }}</a>
      <a href="{{ url('/app/integrations') }}" class="{{ request()->is('app/integrations*') ? 'active' : '' }}">🔌 {{ t('side.integrations') }}</a>
      <a href="{{ url('/app/business-setup') }}" class="{{ request()->is('app/business-setup*') ? 'active' : '' }}">🧩 {{ t('side.business_setup') }}</a>
      <a href="{{ url('/app/settings') }}" class="{{ request()->is('app/settings*') ? 'active' : '' }}">⚙️ {{ t('side.settings') }}</a>
    </nav>
    <div class="foot">
      <a href="{{ url('/') }}" style="color:#a9c4bd">← {{ t('side.back_site') }}</a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="who">{{ auth()->user()->name }} · <span class="badge badge-blue">{{ auth()->user()->roleShortLabel() }}</span></div>
      <div class="header-actions">
        <a class="lang-switch" href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}</a>
        <form method="post" action="{{ url('/logout') }}" style="margin:0">
          @csrf
          <button type="submit" class="btn btn-light btn-sm">{{ t('side.logout') }}</button>
        </form>
      </div>
    </div>
    @if (session('impersonator_admin_id'))
      <div style="background:#3d2b0a;color:#f5d78e;padding:10px 24px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <span>🕵️ {{ t('side.impersonation_banner') }}</span>
        <form method="post" action="/app/end-impersonation" style="margin:0;">
          @csrf
          <button type="submit" class="btn btn-sm" style="background:#f5d78e;color:#3d2b0a;border:none;">{{ t('side.return_to_admin') }}</button>
        </form>
      </div>
    @endif
    <div class="content">
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
  </div>
</div>
<script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
<script>
  // Field supervisors work from a phone on site — installable + a safe static-asset cache
  // makes the panel launchable like an app and keeps loading fast on a weak connection.
  // This never caches authenticated page content — see public/sw.js.
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () { /* installability is a bonus, never block the app */ });
    });
  }
</script>
</body>
</html>
