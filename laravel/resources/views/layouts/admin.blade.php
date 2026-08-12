<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@hasSection('title')@yield('title') · @endif Admin · BuildXact Saudi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar" style="background:#151f1e">
    <div class="brand"><span class="mark" style="background:#fff;color:#151f1e">BX</span> Platform Admin</div>
    <nav>
      <a href="{{ url('/admin') }}" class="{{ request()->is('admin') ? 'active' : '' }}">📊 {{ t('aside.dashboard') }}</a>
      <a href="{{ url('/admin/reports') }}" class="{{ request()->is('admin/reports*') ? 'active' : '' }}">📈 {{ t('aside.reports') }}</a>
      <a href="{{ url('/admin/companies') }}" class="{{ request()->is('admin/companies*') ? 'active' : '' }}">🏢 {{ t('aside.companies') }}</a>
      <a href="{{ url('/admin/plans') }}" class="{{ request()->is('admin/plans*') ? 'active' : '' }}">📦 {{ t('aside.plans') }}</a>
      <a href="{{ url('/admin/payments') }}" class="{{ request()->is('admin/payments*') ? 'active' : '' }}">💵 {{ t('aside.payments') }}</a>
      <a href="{{ url('/admin/integrations') }}" class="{{ request()->is('admin/integrations*') ? 'active' : '' }}">🔌 {{ t('aside.integrations') }}</a>
      <a href="{{ url('/admin/quick-estimate') }}" class="{{ request()->is('admin/quick-estimate*') ? 'active' : '' }}">🧮 {{ t('aside.quick_estimate') }}</a>
      <a href="{{ url('/admin/estimate-templates') }}" class="{{ request()->is('admin/estimate-templates*') ? 'active' : '' }}">📐 {{ t('aside.estimate_templates') }}</a>
      <div class="nav-section">{{ t('aside.section_website') }}</div>
      <a href="{{ url('/admin/pages') }}" class="{{ request()->is('admin/pages*') ? 'active' : '' }}">📄 {{ t('aside.pages') }}</a>
      <a href="{{ url('/admin/settings/header') }}" class="{{ request()->is('admin/settings/header*') ? 'active' : '' }}">🖼️ {{ t('aside.header_footer') }}</a>
      <a href="{{ url('/admin/translations') }}" class="{{ request()->is('admin/translations*') ? 'active' : '' }}">🌐 {{ t('aside.translations') }}</a>
      <div class="nav-section">{{ t('aside.section_platform') }}</div>
      <a href="{{ url('/admin/usage') }}" class="{{ request()->is('admin/usage*') ? 'active' : '' }}">📊 {{ t('aside.usage') }}</a>
      <a href="{{ url('/admin/consultations') }}" class="{{ request()->is('admin/consultations*') ? 'active' : '' }}">🎓 {{ t('aside.consultations') }}</a>
      <a href="{{ url('/admin/support') }}" class="{{ request()->is('admin/support*') ? 'active' : '' }}">🎧 {{ t('aside.support') }}</a>
      <a href="{{ url('/admin/certificates') }}" class="{{ request()->is('admin/certificates*') ? 'active' : '' }}">🏅 {{ t('aside.certificates') }}</a>
      <a href="{{ url('/admin/audit-log') }}" class="{{ request()->is('admin/audit-log*') ? 'active' : '' }}">🧾 {{ t('aside.audit_log') }}</a>
      <a href="{{ url('/admin/admins') }}" class="{{ request()->is('admin/admins*') ? 'active' : '' }}">🛡️ {{ t('aside.admins') }}</a>
      <a href="{{ url('/admin/settings') }}" class="{{ request()->is('admin/settings') ? 'active' : '' }}">⚙️ {{ t('aside.settings') }}</a>
      <a href="{{ url('/admin/profile') }}" class="{{ request()->is('admin/profile*') ? 'active' : '' }}">👤 {{ t('aside.my_profile') }}</a>
    </nav>
    <div class="foot">
      <a href="{{ url('/') }}" style="color:#a9c4bd">← {{ t('side.back_site') }}</a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="who"><a href="{{ url('/admin/profile') }}" style="color:inherit;text-decoration:none;">{{ auth()->user()->name }}</a> · <span class="badge badge-gray">{{ auth()->user()->isSuperAdmin() ? t('aside.super_admin_badge') : \App\Models\User::ADMIN_ROLES['support_admin'] }}</span></div>
      <div class="header-actions">
        <a class="lang-switch" href="?lang={{ app()->getLocale() === 'ar' ? 'en' : 'ar' }}">{{ app()->getLocale() === 'ar' ? 'EN' : 'AR' }}</a>
        <form method="post" action="{{ url('/logout') }}" style="margin:0">
          @csrf
          <button type="submit" class="btn btn-light btn-sm">{{ t('side.logout') }}</button>
        </form>
      </div>
    </div>
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
</body>
</html>
