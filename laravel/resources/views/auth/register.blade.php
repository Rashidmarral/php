<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register · BuildXact Saudi</title>
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="auth-body">
  <div class="auth-card card" style="max-width:460px;margin:60px auto;">
    <h1 style="font-size:20px;">Start your free trial</h1>
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
      <div class="form-group"><label>Company name</label><input type="text" name="company_name" value="{{ old('company_name') }}" required></div>
      <div class="form-group"><label>Your name</label><input type="text" name="name" value="{{ old('name') }}" required></div>
      <div class="form-group"><label>{{ t('common.email') }}</label><input type="email" name="email" value="{{ old('email') }}" required></div>
      <div class="form-group"><label>{{ t('common.password') }}</label><input type="password" name="password" required minlength="8"></div>
      <div class="form-group">
        <label>{{ t('common.plan') }}</label>
        <select name="plan" required>
          @foreach ($plans as $plan)
            <option value="{{ $plan->slug }}">{{ $plan->name }} — {{ $plan->price_monthly }} SAR/mo</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create account</button>
    </form>
    <p class="help-text" style="margin-top:14px;"><a href="{{ url('/login') }}">Already have an account? Log in</a></p>
  </div>
</body>
</html>
