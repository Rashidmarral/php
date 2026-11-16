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
  <div class="auth-split auth-split--wide">
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

        <div class="auth-form-section">
          <div class="auth-form-section-title"><span class="num">1</span> {{ t('auth.section_company') }}</div>
          <div class="form-group">
            <label>{{ t('auth.company_name') }}</label>
            <input type="text" name="company_name" value="{{ old('company_name') }}" placeholder="Al Rashid Construction Co." required>
          </div>
          <div class="form-group">
            <label>{{ t('auth.company_name_ar') }} <span class="help-text" style="display:inline;">({{ t('common.optional') }})</span></label>
            <input type="text" name="company_name_ar" value="{{ old('company_name_ar') }}" dir="rtl" placeholder="شركة الراشد للمقاولات">
            <p class="help-text">{{ t('auth.company_name_ar_hint') }}</p>
          </div>
          <div class="form-row">
            <div class="form-group"><label>{{ t('auth.phone') }}</label><input type="tel" name="phone" value="{{ old('phone') }}" placeholder="+966 5x xxx xxxx"></div>
            <div class="form-group"><label>{{ t('auth.city') }}</label><input type="text" name="city" value="{{ old('city') }}" placeholder="Riyadh"></div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>{{ t('auth.cr_number') }} <span class="help-text" style="display:inline;">({{ t('common.optional') }})</span></label>
              <input type="text" name="cr_number" value="{{ old('cr_number') }}" placeholder="1010XXXXXX">
            </div>
            <div class="form-group">
              <label>{{ t('auth.team_size') }}</label>
              <select name="team_size">
                <option value="">{{ t('auth.team_size_placeholder') }}</option>
                <option value="1-5">1–5</option>
                <option value="6-15">6–15</option>
                <option value="16-50">16–50</option>
                <option value="51-200">51–200</option>
                <option value="200+">200+</option>
              </select>
            </div>
          </div>
        </div>

        <div class="auth-form-section">
          <div class="auth-form-section-title"><span class="num">2</span> {{ t('auth.section_account') }}</div>
          <div class="form-group">
            <label>{{ t('auth.your_name') }}</label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="Enter your full name" required>
          </div>
          <div class="form-group">
            <label>{{ t('common.email') }}</label>
            <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" required>
          </div>
          <div class="form-group">
            <label>{{ t('common.plan') }}</label>
            <select name="plan" required>
              @foreach ($plans as $plan)
                <option value="{{ $plan->slug }}">{{ $plan->name }} — {{ $plan->price_monthly }} SAR/mo</option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="auth-form-section">
          <div class="auth-form-section-title"><span class="num">3</span> {{ t('auth.section_security') }}</div>
          <div class="form-group">
            <label>{{ t('common.password') }}</label>
            <div class="password-field">
              <input type="password" name="password" required minlength="8" data-password-strength-input>
              {!! passwordToggle() !!}
            </div>
            <div class="password-strength">
              <div class="password-strength-track"><div class="password-strength-fill" data-password-strength-fill></div></div>
              <span class="password-strength-label" data-password-strength-label data-labels='{"weak":"{{ t('auth.password_strength_weak') }}","fair":"{{ t('auth.password_strength_fair') }}","good":"{{ t('auth.password_strength_good') }}","strong":"{{ t('auth.password_strength_strong') }}"}'></span>
            </div>
            <p class="help-text">{{ t('auth.password_hint') }}</p>
          </div>
          <div class="form-group">
            <label>{{ t('auth.confirm_password') }}</label>
            <div class="password-field">
              <input type="password" name="password_confirmation" required minlength="8">
              {!! passwordToggle() !!}
            </div>
          </div>
          <div class="auth-security-note">
            🔒 <span>{{ t('auth.two_factor_security_note') }}</span>
          </div>
        </div>

        <label class="auth-checkbox-row">
          <input type="checkbox" name="terms" value="1" required>
          <span>{!! t('auth.agree_terms', ['terms' => '<a href="' . url('/terms') . '" target="_blank" rel="noopener">' . t('auth.terms_link_text') . '</a>', 'privacy' => '<a href="' . url('/privacy') . '" target="_blank" rel="noopener">' . t('auth.privacy_link_text') . '</a>']) !!}</span>
        </label>

        <button type="submit" class="btn btn-primary btn-block">{{ t('auth.register_btn') }}</button>
      </form>

      <p class="auth-foot-link">{{ t('auth.have_account') }} <a href="{{ url('/login') }}">{{ t('auth.sign_in') }}</a></p>
    </div>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
  <script src="{{ asset('assets/js/password-strength.js') }}" defer></script>
</body>
</html>
