<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Page not found · BuildXact Saudi</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap">
<link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:24px;">
  <div>
    <div style="font-size:76px;font-weight:800;color:var(--brand,#1b6e4f);line-height:1;margin-bottom:8px;">404</div>
    <h1 style="font-size:22px;margin-bottom:8px;">{{ app()->getLocale() === 'ar' ? 'الصفحة غير موجودة' : "This page doesn't exist" }}</h1>
    <p style="color:var(--muted,#6b7a75);max-width:420px;margin:0 auto 24px;">
      {{ app()->getLocale() === 'ar' ? 'الرابط الذي اتبعته قد يكون قديماً أو غير صحيح.' : 'The link you followed may be broken, or the page may have moved.' }}
    </p>
    <a href="{{ url('/') }}" class="btn btn-primary">{{ app()->getLocale() === 'ar' ? 'العودة للرئيسية' : 'Back to homepage' }}</a>
  </div>
</div>
</body>
</html>
