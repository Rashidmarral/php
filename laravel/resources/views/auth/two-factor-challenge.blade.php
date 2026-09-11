<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ t('auth.two_factor_title') }} · {{ \App\Models\Setting::siteName() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
  <div class="auth-split">
    <div class="auth-split-form">
      <div class="logo"><span class="mark" style="background:var(--brand);color:#fff;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;">BX</span> <strong style="font-size:18px;">{{ \App\Models\Setting::siteName() }}</strong></div>

      <h1>{{ t('auth.two_factor_title') }}</h1>
      <p class="sub">{{ t('auth.two_factor_sub') }}</p>

      @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif
      @if (session('flash.error'))
        @foreach ((array) session('flash.error') as $m)
          <div class="alert alert-error">{{ $m }}</div>
        @endforeach
      @endif

      <form method="post" action="{{ url('/login/2fa') }}">
        @csrf
        <div class="form-group">
          <label>{{ t('auth.two_factor_code_label') }}</label>
          <input type="text" name="code" inputmode="numeric" placeholder="123456" autocomplete="one-time-code" required autofocus>
        </div>
        <button type="submit" class="btn btn-dark btn-block">{{ t('auth.two_factor_verify') }}</button>
      </form>

      <p class="auth-foot-link">{{ t('auth.two_factor_recovery_hint') }}</p>
    </div>

    <div class="auth-split-panel">
      <div class="auth-split-logo"><span class="mark">BX</span> {{ \App\Models\Setting::siteName() }}</div>
      <div>
        <div class="auth-split-badge">✓ {{ t('auth.zatca_badge') }}</div>
        <h2>{{ t('auth.panel_heading') }}</h2>
        <p class="desc">{{ t('auth.panel_desc') }}</p>
      </div>
      <div class="auth-split-foot">
        <span>&copy; {{ date('Y') }} {{ \App\Models\Setting::siteName() }}</span>
        <span>{{ t('auth.panel_location') }}</span>
      </div>
    </div>
  </div>
</body>
</html>
