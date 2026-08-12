@extends('layouts.site')

@php($isAr = app()->getLocale() === 'ar')

@section('content')
<section class="hero" style="padding-bottom:40px;">
  @include('partials.hero-media', ['page' => 'support'])
  <div class="container" style="grid-template-columns:1fr;max-width:720px;text-align:center;">
    <div>
      <span class="hero-badge">🎧 {{ $isAr ? 'الدعم' : 'Support' }}</span>
      <h1>{{ $isAr ? 'مركز المساعدة' : 'Help Center' }}</h1>
      <p class="lead" style="max-width:640px;margin-left:auto;margin-right:auto;">
        {{ $isAr ? 'عميل حالي؟ افتح تذكرة دعم من لوحة شركتك أو بوابة العميل وسنرد عليك بسرعة.' : "Already a customer? Open a support ticket from your company panel or client portal and we'll respond quickly." }}
      </p>
      <div class="hero-actions" style="justify-content:center;">
        <a href="{{ url('/app/support') }}" class="btn btn-primary">{{ $isAr ? 'دخول لوحة الشركة' : 'Company panel login' }}</a>
        <a href="{{ url('/portal/login') }}" class="btn btn-outline-light">{{ $isAr ? 'دخول بوابة العميل' : 'Client portal login' }}</a>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal-stagger" style="max-width:960px;margin:0 auto 40px;">
      <div class="card feature-card reveal">
        <div class="icon">🎫</div>
        <h3>{{ $isAr ? 'تذكرة دعم' : 'Open a ticket' }}</h3>
        <p>{{ $isAr ? 'أصحاب الشركات والموظفون يفتحون تذكرة مباشرة من اللوحة، مع إمكانية إرفاق صور أو ملفات.' : 'Company owners and staff open a ticket right from their panel, with image/file attachments if needed.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🧑‍🤝‍🧑</div>
        <h3>{{ $isAr ? 'عملاؤك مدعومون أيضاً' : 'Your clients are covered too' }}</h3>
        <p>{{ $isAr ? 'يمكن لعملائك فتح تذكرة من بوابة العميل الخاصة بهم، وستصل مباشرة إليك لحلّها.' : 'Your own clients can raise a request from their client portal — it comes straight to you to resolve.' }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">⭐</div>
        <h3>{{ $isAr ? 'دعم ذو أولوية' : 'Priority support' }}</h3>
        <p>{{ $isAr ? 'باقتا Professional وEnterprise تحصلان على أولوية استجابة أسرع.' : 'Professional and Enterprise plans get faster response times on every ticket.' }}</p>
      </div>
    </div>

    <div class="section-head">
      <div class="eyebrow">{{ $isAr ? 'أسئلة شائعة' : 'Frequently asked' }}</div>
      <h2>{{ $isAr ? 'إجابات سريعة' : 'Quick answers' }}</h2>
    </div>
    <div class="faq-list reveal-section reveal">
      <details class="faq-item" open>
        <summary>{{ $isAr ? 'كيف أفتح تذكرة دعم؟' : 'How do I open a support ticket?' }}</summary>
        <div class="answer">{{ $isAr ? 'سجّل الدخول للوحتك، ثم من القائمة الجانبية اختر "الدعم" وأنشئ طلبًا جديدًا مع وصف المشكلة وأي مرفقات.' : 'Log in to your panel, choose "Support" in the sidebar, and submit a new request with a description and any attachments.' }}</div>
      </details>
      <details class="faq-item">
        <summary>{{ $isAr ? 'ما الفرق بين التجربة المجانية والاشتراك؟' : "What's the difference between the free trial and a subscription?" }}</summary>
        <div class="answer">{{ $isAr ? 'كل حساب جديد يحصل على تجربة مجانية لعدة أيام بكامل الميزات، ثم يمكنك الاشتراك من صفحة الفوترة داخل لوحتك.' : 'Every new account starts with a full-featured free trial. When it ends, subscribe from the Billing page inside your panel.' }}</div>
      </details>
      <details class="faq-item">
        <summary>{{ $isAr ? 'هل تدعمون الفوترة الإلكترونية (فاتورة)؟' : 'Do you support ZATCA e-invoicing (Fatoora)?' }}</summary>
        <div class="answer">{{ $isAr ? 'نعم، جميع الباقات تدعم رمز الاستجابة السريعة (المرحلة الأولى)، وباقتا Professional وEnterprise تدعمان المرحلة الثانية الكاملة.' : 'Yes — every plan includes ZATCA Phase 1 QR codes, and Professional/Enterprise include full Phase 2 (Fatoora) integration.' }}</div>
      </details>
      <details class="faq-item">
        <summary>{{ $isAr ? 'ماذا لو نسيت كلمة المرور؟' : 'What if I forgot my password?' }}</summary>
        <div class="answer">{{ $isAr ? 'استخدم رابط "نسيت كلمة المرور" في صفحة تسجيل الدخول لإعادة تعيينها عبر بريدك الإلكتروني.' : 'Use "Forgot password" on the login page to reset it via email.' }}</div>
      </details>
    </div>

    <div class="reveal-section reveal" style="text-align:center;margin-top:36px;">
      <p style="color:var(--muted);">{{ $isAr ? 'لا تزال بحاجة إلى مساعدة؟' : 'Still need help?' }}</p>
      <a href="{{ url('/contact') }}" class="btn btn-outline">{{ $isAr ? 'تواصل معنا' : 'Contact us' }}</a>
    </div>
  </div>
</section>
@endsection
