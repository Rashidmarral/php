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
<body class="auth-body">
  <div class="auth-card card" style="max-width:420px;margin:80px auto;">
    <h1 style="font-size:20px;">{{ \App\Models\Setting::siteName() }}</h1>
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
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
      </div>
      <div class="form-group">
        <label>{{ t('common.password') }}</label>
        <div class="password-field">
          <input type="password" name="password" required>
          {!! passwordToggle() !!}
        </div>
      </div>
      <p style="margin:-6px 0 14px;text-align:end;font-size:13.5px;">
        <a href="{{ url('/forgot-password') }}">{{ t('auth.forgot_password') }}</a>
      </p>
      <button type="submit" class="btn btn-primary btn-block">{{ t('common.submit') }}</button>
    </form>
    <p class="help-text" style="margin-top:14px;">
      <a href="{{ url('/register') }}">Create an account</a>
    </p>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
</body>
</html>
