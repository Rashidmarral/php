<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Something went wrong · BuildXact Saudi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;">
  <div>
    <div style="font-size:76px;font-weight:800;color:var(--danger,#c0392b);line-height:1;margin-bottom:8px;">500</div>
    <h1 style="font-size:22px;margin-bottom:8px;">{{ app()->getLocale() === 'ar' ? 'حدث خطأ ما' : 'Something went wrong on our end' }}</h1>
    <p style="color:var(--muted,#6b7a75);max-width:420px;margin:0 auto 24px;">
      {{ app()->getLocale() === 'ar' ? 'فريقنا تم إبلاغه. الرجاء المحاولة مرة أخرى بعد قليل.' : "Our team has been notified. Please try again in a moment." }}
    </p>
    <a href="{{ url('/') }}" class="btn btn-primary">{{ app()->getLocale() === 'ar' ? 'العودة للرئيسية' : 'Back to homepage' }}</a>
  </div>
</div>
</body>
</html>
