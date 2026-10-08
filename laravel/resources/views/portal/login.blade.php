<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Client Portal · {{ \App\Models\Setting::siteName() }}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="auth-body">
  <div class="auth-card card" style="max-width:420px;margin:80px auto;">
    <h1 style="font-size:20px;">Client Portal</h1>
    <p class="help-text" style="margin-bottom:20px;">Log in to view your projects, estimates, and invoices.</p>
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
    <form method="post" action="{{ url('/portal/login') }}">
      @csrf
      <div class="form-group"><label>Email address</label><input type="email" name="email" required autofocus></div>
      <div class="form-group">
        <label>Password</label>
        <div class="password-field">
          <input type="password" name="password" required>
          {!! passwordToggle() !!}
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="help-text" style="margin-top:14px;">Your contractor gives you access to the client portal — contact them if you don't have a login yet.</p>
  </div>
  <script src="{{ asset('assets/js/password-toggle.js') }}" defer></script>
</body>
</html>
