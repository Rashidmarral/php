@extends('layouts.site')

@php($isAr = app()->getLocale() === 'ar')
@php($siteName = \App\Models\Setting::siteName())

@section('content')
<section class="hero" style="padding-bottom:40px;">
  <div class="container" style="grid-template-columns:1fr;max-width:760px;text-align:center;">
    <div>
      <span class="hero-badge">🔒 {{ $isAr ? 'الأمان والامتثال' : 'Security & Compliance' }}</span>
      <h1>{{ $isAr ? 'بياناتك محمية وموثوقة' : 'Your data, protected and compliant' }}</h1>
      <p class="lead" style="max-width:680px;margin-left:auto;margin-right:auto;">
        {{ $isAr ? "بُنيت {$siteName} لتلبية متطلبات المقاولين السعوديين — من التوافق مع هيئة الزكاة والضريبة والجمارك إلى حماية بيانات عملائك." : "{$siteName} is built for Saudi contractors — from ZATCA compliance to keeping your project and client data safe." }}
      </p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal-stagger" style="max-width:1000px;margin:0 auto;">
      <div class="card feature-card reveal">
        <div class="icon">🧾</div>
        <h3>{{ $isAr ? 'متوافق مع فاتورة (ZATCA)' : 'ZATCA-compliant e-invoicing' }}</h3>
        <p>{{ $isAr ? 'رمز الاستجابة السريعة للمرحلة الأولى على كل فاتورة، ودمج كامل للمرحلة الثانية (تقارير/تكامل) لباقات Professional وEnterprise.' : 'Phase 1 QR codes on every invoice, and full Phase 2 (reporting/integration) e-invoicing on Professional and Enterprise plans.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🔐</div>
        <h3>{{ $isAr ? 'عزل بيانات كل شركة' : 'Company-level data isolation' }}</h3>
        <p>{{ $isAr ? 'كل شركة تعمل ضمن مساحة بيانات معزولة تمامًا — لا يمكن لأي شركة أخرى الوصول إلى مشاريعك أو عملائك أو فواتيرك.' : 'Every company operates in a fully isolated data space — no other company can ever see your projects, clients, or invoices.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🔑</div>
        <h3>{{ $isAr ? 'تحكم بالأدوار والصلاحيات' : 'Role-based access control' }}</h3>
        <p>{{ $isAr ? 'حدد بدقة من يرى الفواتير، ومن يعدّل التقديرات، ومن لديه صلاحية العرض فقط داخل فريقك.' : 'Decide exactly who on your team can see invoices, edit estimates, or only view — down to the individual role.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🔒</div>
        <h3>{{ $isAr ? 'كلمات مرور مشفّرة' : 'Encrypted passwords' }}</h3>
        <p>{{ $isAr ? 'جميع كلمات المرور مشفّرة بخوارزميات معتمدة في الصناعة، ولا يمكن لأي موظف رؤيتها كنص صريح.' : 'Every password is hashed with industry-standard algorithms — nobody on our team can ever see it in plain text.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">✍️</div>
        <h3>{{ $isAr ? 'روابط مشاركة آمنة' : 'Secure share links' }}</h3>
        <p>{{ $isAr ? 'روابط توقيع العروض ودفع الفواتير تستخدم رموزًا عشوائية طويلة يتعذّر تخمينها.' : 'Estimate-signing and invoice-payment links use long, unguessable random tokens — not sequential IDs.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">💾</div>
        <h3>{{ $isAr ? 'بياناتك تبقى لك' : 'You always own your data' }}</h3>
        <p>{{ $isAr ? 'يمكنك تصدير بيانات شركتك في أي وقت، ولا نشارك بياناتك مع أي طرف ثالث لأغراض تسويقية.' : 'Export your company data any time. We never sell or share your data with third parties for marketing.' }}</p>
      </div>
    </div>

    <div class="reveal-section reveal" style="text-align:center;margin-top:44px;">
      <p style="color:var(--muted);">{{ $isAr ? 'لديك سؤال أمني أو متطلب امتثال محدد؟' : 'Have a specific security or compliance question?' }}</p>
      <a href="{{ url('/contact') }}" class="btn btn-outline">{{ $isAr ? 'تواصل مع فريقنا' : 'Talk to our team' }}</a>
    </div>
  </div>
</section>
@endsection
