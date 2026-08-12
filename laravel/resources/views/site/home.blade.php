@extends('layouts.site')

@section('content')
<section class="hero">
  <div class="container">
    <div>
      <span class="hero-badge">🇸🇦 {{ t('hero.badge') }}</span>
      <h1>{{ t('hero.title') }}</h1>
      <p class="lead">{{ t('hero.lead') }}</p>
      <div class="hero-actions">
        <a href="{{ url('/register') }}" class="btn btn-primary">{{ t('hero.cta_primary') }}</a>
        <a href="{{ url('/features') }}" class="btn btn-outline">{{ t('hero.cta_secondary') }}</a>
      </div>
    </div>
    <div class="hero-visual">
      <div class="kpi-grid" style="grid-template-columns:1fr 1fr;margin-bottom:0;">
        <div class="kpi"><div class="label">Active Projects</div><div class="value">18</div><div class="delta">+3 this month</div></div>
        <div class="kpi"><div class="label">Est. Accuracy</div><div class="value">96%</div><div class="delta">+4% vs manual</div></div>
        <div class="kpi"><div class="label">Outstanding</div><div class="value">SAR 128K</div></div>
        <div class="kpi"><div class="label">Paid this month</div><div class="value">SAR 412K</div></div>
      </div>
      <div class="stat-row">
        <div><strong>2,400+</strong><span>{{ t('hero.stat1') }}</span></div>
        <div><strong>15,000+</strong><span>{{ t('hero.stat2') }}</span></div>
        <div><strong>6 hrs/wk</strong><span>{{ t('hero.stat3') }}</span></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('features.eyebrow') }}</div>
      <h2>{{ t('features.title') }}</h2>
      <p style="color:var(--muted)">{{ t('features.subtitle') }}</p>
    </div>
    <div class="grid grid-3 reveal-stagger">
      <div class="card feature-card reveal"><div class="icon">🧮</div><h3>{{ t('features.estimating.title') }}</h3><p>{{ t('features.estimating.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📈</div><h3>{{ t('features.jobcosting.title') }}</h3><p>{{ t('features.jobcosting.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📅</div><h3>{{ t('features.scheduling.title') }}</h3><p>{{ t('features.scheduling.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">💳</div><h3>{{ t('features.invoicing.title') }}</h3><p>{{ t('features.invoicing.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">👥</div><h3>{{ t('features.clients.title') }}</h3><p>{{ t('features.clients.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🤝</div><h3>{{ t('features.team.title') }}</h3><p>{{ t('features.team.desc') }}</p></div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('platform.eyebrow') }}</div>
      <h2>{{ t('platform.title') }}</h2>
      <p style="color:var(--muted)">{{ t('platform.subtitle') }}</p>
    </div>
    <div class="grid grid-4 reveal-stagger">
      <div class="card feature-card reveal"><div class="icon">⚡</div><h3>{{ t('platform.quick_estimate.title') }}</h3><p>{{ t('platform.quick_estimate.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📐</div><h3>{{ t('platform.takeoff.title') }}</h3><p>{{ t('platform.takeoff.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🚚</div><h3>{{ t('platform.suppliers.title') }}</h3><p>{{ t('platform.suppliers.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📁</div><h3>{{ t('platform.documents.title') }}</h3><p>{{ t('platform.documents.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📈</div><h3>{{ t('platform.reports.title') }}</h3><p>{{ t('platform.reports.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🌐</div><h3>{{ t('platform.portal.title') }}</h3><p>{{ t('platform.portal.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🧾</div><h3>{{ t('platform.zatca.title') }}</h3><p>{{ t('platform.zatca.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📄</div><h3>{{ t('platform.pdf.title') }}</h3><p>{{ t('platform.pdf.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">💳</div><h3>{{ t('platform.payments.title') }}</h3><p>{{ t('platform.payments.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🔐</div><h3>{{ t('platform.plans.title') }}</h3><p>{{ t('platform.plans.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🔌</div><h3>{{ t('platform.integrations.title') }}</h3><p>{{ t('platform.integrations.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🛡️</div><h3>{{ t('platform.admin.title') }}</h3><p>{{ t('platform.admin.desc') }}</p></div>
    </div>
  </div>
</section>

<section class="section reveal-section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('platform2.eyebrow') }}</div>
      <h2>{{ t('platform2.title') }}</h2>
      <p style="color:var(--muted)">{{ t('platform2.subtitle') }}</p>
    </div>
    <div class="grid grid-3">
      <div class="card feature-card reveal"><div class="icon">💬</div><h3>{{ t('platform2.whatsapp.title') }}</h3><p>{{ t('platform2.whatsapp.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">✍️</div><h3>{{ t('platform2.esign.title') }}</h3><p>{{ t('platform2.esign.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📝</div><h3>{{ t('platform2.changeorders.title') }}</h3><p>{{ t('platform2.changeorders.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">📸</div><h3>{{ t('platform2.photodiary.title') }}</h3><p>{{ t('platform2.photodiary.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🏛️</div><h3>{{ t('platform2.compliance.title') }}</h3><p>{{ t('platform2.compliance.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🧾</div><h3>{{ t('platform2.saudi_invoice.title') }}</h3><p>{{ t('platform2.saudi_invoice.desc') }}</p></div>
    </div>
  </div>
</section>

<section class="section reveal-section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('platform3.eyebrow') }}</div>
      <h2>{{ t('platform3.title') }}</h2>
      <p style="color:var(--muted)">{{ t('platform3.subtitle') }}</p>
    </div>
    <div class="grid grid-4">
      <div class="card feature-card reveal"><div class="icon">📋</div><h3>{{ t('platform3.templates.title') }}</h3><p>{{ t('platform3.templates.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">✨</div><h3>{{ t('platform3.ai.title') }}</h3><p>{{ t('platform3.ai.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🧑‍💼</div><h3>{{ t('platform3.roles.title') }}</h3><p>{{ t('platform3.roles.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🎯</div><h3>{{ t('platform3.leads.title') }}</h3><p>{{ t('platform3.leads.desc') }}</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('how.eyebrow') }}</div>
      <h2>{{ t('how.title') }}</h2>
    </div>
    <div class="how-steps reveal-stagger">
      <div class="how-step reveal"><div class="num">1</div><h3>{{ t('how.step1.title') }}</h3><p>{{ t('how.step1.desc') }}</p></div>
      <div class="how-step reveal"><div class="num">2</div><h3>{{ t('how.step2.title') }}</h3><p>{{ t('how.step2.desc') }}</p></div>
      <div class="how-step reveal"><div class="num">3</div><h3>{{ t('how.step3.title') }}</h3><p>{{ t('how.step3.desc') }}</p></div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('testi.eyebrow') }}</div>
      <h2>{{ t('testi.title') }}</h2>
    </div>
    <div class="grid grid-3 reveal-stagger">
      <div class="testi-card reveal">
        <div class="stars">★★★★★</div>
        <p class="quote">"{{ t('testi1.quote') }}"</p>
        <div class="author"><div class="avatar">AQ</div><div><div class="name">{{ t('testi1.name') }}</div><div class="role">{{ t('testi1.role') }}</div></div></div>
      </div>
      <div class="testi-card reveal">
        <div class="stars">★★★★★</div>
        <p class="quote">"{{ t('testi2.quote') }}"</p>
        <div class="author"><div class="avatar">FZ</div><div><div class="name">{{ t('testi2.name') }}</div><div class="role">{{ t('testi2.role') }}</div></div></div>
      </div>
      <div class="testi-card reveal">
        <div class="stars">★★★★★</div>
        <p class="quote">"{{ t('testi3.quote') }}"</p>
        <div class="author"><div class="avatar">MD</div><div><div class="name">{{ t('testi3.name') }}</div><div class="role">{{ t('testi3.role') }}</div></div></div>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('faq.eyebrow') }}</div>
      <h2>{{ t('faq.title') }}</h2>
    </div>
    <div class="faq-list reveal-section reveal">
      @foreach ([1,2,3,4,5] as $i)
        <details class="faq-item">
          <summary>{{ t("faq{$i}.q") }}</summary>
          <div class="answer">{{ t("faq{$i}.a") }}</div>
        </details>
      @endforeach
    </div>
  </div>
</section>

<section class="section" style="border-top:1px solid var(--border);">
  <div class="container reveal-section reveal" style="text-align:center;">
    <h2>{{ t('cta.title') }}</h2>
    <p style="color:var(--muted)">{{ t('cta.subtitle') }}</p>
    <a href="{{ url('/register') }}" class="btn btn-primary" style="margin-top:10px;">{{ t('cta.button') }}</a>
  </div>
</section>
@endsection
