<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register · {{ \App\Models\Setting::siteName() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
  <div class="auth-split">
    <div class="auth-split-panel">
      <div class="auth-split-logo"><span class="mark">BX</span> {{ \App\Models\Setting::siteName() }}</div>
      <div>
        <h2>{{ t('auth.register_panel_heading') }}</h2>
        <p class="desc">{{ t('auth.register_panel_desc') }}</p>
      </div>
      <div class="auth-split-badge">✓ {{ t('auth.regulated_badge') }}</div>
    </div>

    <div class="auth-split-form">
      <div class="logo"><span class="mark" style="background:var(--brand);color:#fff;width:32px;height:32px;border-radius:8px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;">BX</span> <strong style="font-size:18px;">{{ \App\Models\Setting::siteName() }}</strong></div>

      <h1>{{ t('auth.register_title') }}</h1>
      <p class="sub">{{ t('auth.register_sub') }}</p>

      @if ($errors->any())
        <div class="alert alert-error">
          <ul style="margin:0;padding-inline-start:18px;">
            @foreach ($errors->all() as $error)
              <li>{{ $error }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <form method="post" action="{{ url('/register') }}">
        @csrf
        <div class="form-row">
          <div class="form-group"><label>{{ t('auth.company_name') }}</label><input type="text" name="company_name" value="{{ old('company_name') }}" placeholder="Al Rashid Construction Co." required></div>
          <div class="form-group"><label>{{ t('auth.your_name') }}</label><input type="text" name="name" value="{{ old('name') }}" placeholder="Enter your full name" required></div>
        </div>
        <div class="form-group"><label>{{ t('common.email') }}</label><input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" required></div>
        <div class="form-group">
          <label>{{ t('common.password') }}</label>
          <div class="password-field">
            <input type="password" name="password" required minlength="8">
            {!! passwordToggle() !!}
          </div>
        </div>
        <div class="form-group">
          <label>{{ t('common.plan') }}</label>
          <select name="plan" required>
            @foreach ($plans as $plan)
              <option value="{{ $plan->slug }}">{{ $plan->name }} — {{ $plan->price_monthly }} SAR/mo</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">{{ t('auth.register_btn') }}</button>
      </form>

      <p class="auth-foot-link">{{ t('auth.have_account') }} <a href="{{ url('/login') }}">{{ t('auth.sign_in') }}</a></p>
    </div>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
</body>
</html>
