<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Set a new password · BuildXact Saudi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="auth-body">
  <div class="auth-card card" style="max-width:420px;margin:80px auto;">
    <h1 style="font-size:20px;">Set a new password</h1>
    <p class="help-text" style="margin-bottom:20px;">Choose a new password for your account.</p>
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
    <form method="post" action="{{ url('/reset-password/' . $token) }}">
      @csrf
      <div class="form-group">
        <label>New password</label>
        <div class="password-field">
          <input type="password" name="password" required minlength="8" autofocus>
          {!! passwordToggle() !!}
        </div>
      </div>
      <div class="form-group">
        <label>Confirm new password</label>
        <div class="password-field">
          <input type="password" name="password_confirm" required minlength="8">
          {!! passwordToggle() !!}
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Reset password</button>
    </form>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
</body>
</html>
