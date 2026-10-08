@extends('layouts.site')

@section('content')
<section class="hero" style="padding-bottom:40px;">
  @include('partials.hero-media', ['page' => 'about'])
  <div class="container" style="grid-template-columns:1fr;max-width:820px;text-align:center;">
    <div>
      <span class="hero-badge">🇸🇦 {{ t('about.eyebrow') }}</span>
      <h1>{{ t('about.title') }}</h1>
      <p class="lead" style="max-width:720px;margin-left:auto;margin-right:auto;">{{ t('about.lead') }}</p>
    </div>
  </div>
</section>

<section class="section reveal-section reveal" style="padding-top:0;padding-bottom:48px;">
  <div class="container">
    <div class="stat-row" style="max-width:760px;margin-left:auto;margin-right:auto;">
      <div><strong>{{ t('about.stat1.value') }}</strong><span>{{ t('about.stat1.label') }}</span></div>
      <div><strong>{{ t('about.stat2.value') }}</strong><span>{{ t('about.stat2.label') }}</span></div>
      <div><strong>{{ t('about.stat3.value') }}</strong><span>{{ t('about.stat3.label') }}</span></div>
      <div><strong>{{ t('about.stat4.value') }}</strong><span>{{ t('about.stat4.label') }}</span></div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="grid grid-2" style="max-width:1020px;margin:0 auto;align-items:center;gap:40px;">
      <div class="reveal-section reveal">
        <div class="eyebrow">{{ t('about.story.eyebrow') }}</div>
        <h2>{{ t('about.story.title') }}</h2>
        <p style="color:var(--muted);font-size:15.5px;line-height:1.75;">{{ t('about.story.p1') }}</p>
        <p style="color:var(--muted);font-size:15.5px;line-height:1.75;margin-top:16px;">{{ t('about.story.p2') }}</p>
      </div>
      <div class="visual-panel reveal-section reveal" style="min-height:260px;">
        <div class="grid-icons">
          <span>🧮</span><span>📅</span><span>🧾</span>
          <span>🚚</span><span>📸</span><span>🇸🇦</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="grid grid-2 reveal-stagger" style="max-width:920px;margin:0 auto;">
      <div class="card feature-card reveal">
        <div class="icon">🎯</div>
        <h3>{{ t('about.mission.title') }}</h3>
        <p>{{ t('about.mission.desc') }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🔭</div>
        <h3>{{ t('about.vision.title') }}</h3>
        <p>{{ t('about.vision.desc') }}</p>
      </div>
    </div>
  </div>
</section>

<section class="section" style="background:#fff;border-top:1px solid var(--border);border-bottom:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('about.values.eyebrow') }}</div>
      <h2>{{ t('about.values.title') }}</h2>
    </div>
    <div class="grid grid-3 reveal-stagger">
      <div class="card feature-card reveal"><div class="icon">🇸🇦</div><h3>{{ t('about.value1.title') }}</h3><p>{{ t('about.value1.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🧾</div><h3>{{ t('about.value2.title') }}</h3><p>{{ t('about.value2.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🏗️</div><h3>{{ t('about.value3.title') }}</h3><p>{{ t('about.value3.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🤝</div><h3>{{ t('about.value4.title') }}</h3><p>{{ t('about.value4.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🔒</div><h3>{{ t('about.value5.title') }}</h3><p>{{ t('about.value5.desc') }}</p></div>
      <div class="card feature-card reveal"><div class="icon">🚀</div><h3>{{ t('about.value6.title') }}</h3><p>{{ t('about.value6.desc') }}</p></div>
    </div>
  </div>
</section>

@if($certificates->isNotEmpty())
<section class="section" style="background:#fff;border-top:1px solid var(--border);">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('about.certs.eyebrow') }}</div>
      <h2>{{ t('about.certs.title') }}</h2>
      <p style="color:var(--muted)">{{ t('about.certs.subtitle') }}</p>
    </div>
    <div class="grid grid-4 reveal-stagger" style="align-items:stretch;">
      @foreach ($certificates as $cert)
        <div class="card feature-card reveal" style="text-align:center;">
          <img src="{{ $cert->image_path }}" alt="{{ app()->getLocale() === 'ar' && $cert->title_ar ? $cert->title_ar : $cert->title_en }}" style="max-height:64px;max-width:100%;object-fit:contain;margin:0 auto 12px;">
          <h3 style="font-size:14.5px;">{{ app()->getLocale() === 'ar' && $cert->title_ar ? $cert->title_ar : $cert->title_en }}</h3>
          @if($cert->issuer_en)
            <p style="font-size:12.5px;">{{ app()->getLocale() === 'ar' && $cert->issuer_ar ? $cert->issuer_ar : $cert->issuer_en }}</p>
          @endif
        </div>
      @endforeach
    </div>
  </div>
</section>
@endif

<section class="section" style="border-top:1px solid var(--border);">
  <div class="container reveal-section reveal" style="text-align:center;">
    <h2>{{ t('about.cta.title') }}</h2>
    <p style="color:var(--muted)">{{ t('about.cta.subtitle') }}</p>
    <div class="hero-actions" style="justify-content:center;margin-top:16px;">
      <a href="{{ url('/register') }}" class="btn btn-primary">{{ t('hero.cta_primary') }}</a>
      <a href="{{ url('/support') }}" class="btn btn-outline">{{ t('nav.support') }}</a>
    </div>
  </div>
</section>
@include('partials.custom-sections', ['page' => 'about'])
@endsection
