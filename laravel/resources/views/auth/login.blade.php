<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ t('auth.login_title') ?? 'Log in' }} · {{ \App\Models\Setting::siteName() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
  <div class="auth-split">
    <div class="auth-split-form">
      <div class="logo"><span class="mark" style="background:var(--brand);color:#fff;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;">BX</span> <strong style="font-size:18px;">{{ \App\Models\Setting::siteName() }}</strong></div>

      <h1>{{ t('auth.login_title') }}</h1>
      <p class="sub">{{ t('auth.login_sub') }}</p>

      @if ($errors->any())
        <div class="alert alert-error">{{ $errors->first() }}</div>
      @endif
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

      <form method="post" action="{{ url('/login') }}">
        @csrf
        <div class="form-group">
          <label>{{ t('common.email') }}</label>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" required autofocus>
        </div>
        <div class="form-group">
          <label>
            {{ t('common.password') }}
            <a href="{{ url('/forgot-password') }}" style="float:inline-end;font-weight:500;font-size:12.5px;">{{ t('auth.forgot_password') }}</a>
          </label>
          <div class="password-field">
            <input type="password" name="password" required>
            {!! passwordToggle() !!}
          </div>
        </div>
        <button type="submit" class="btn btn-dark btn-block">{{ t('auth.sign_in') }}</button>
      </form>

      <p class="auth-foot-link">{{ t('auth.no_account') }} <a href="{{ url('/register') }}">{{ t('auth.register_link') }}</a></p>
    </div>

    <div class="auth-split-panel">
      <div class="auth-split-logo"><span class="mark">BX</span> {{ \App\Models\Setting::siteName() }}</div>
      <div>
        <div class="auth-split-badge">✓ {{ t('auth.zatca_badge') }}</div>
        <h2>{{ t('auth.panel_heading') }}</h2>
        <p class="desc">{{ t('auth.panel_desc') }}</p>
        <div class="auth-split-stats">
          <div><strong>2,400+</strong><span>{{ t('hero.stat1') }}</span></div>
          <div><strong>15,000+</strong><span>{{ t('hero.stat2') }}</span></div>
        </div>
      </div>
      <div class="auth-split-foot">
        <span>&copy; {{ date('Y') }} {{ \App\Models\Setting::siteName() }}</span>
        <span>{{ t('auth.panel_location') }}</span>
      </div>
    </div>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
</body>
</html>
