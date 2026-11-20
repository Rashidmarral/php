@extends('layouts.site')

@section('content')
<section class="hero" style="padding-bottom:40px;">
  @include('partials.hero-media', ['page' => 'support'])
  <div class="container" style="grid-template-columns:1fr;max-width:720px;text-align:center;">
    <div>
      <span class="hero-badge">🎧 {{ t('support.badge') }}</span>
      <h1>{{ t('support.title') }}</h1>
      <p class="lead" style="max-width:640px;margin-left:auto;margin-right:auto;">{{ t('support.lead') }}</p>
      <div class="hero-actions" style="justify-content:center;">
        <a href="{{ url('/app/support') }}" class="btn btn-primary">{{ t('support.cta_company') }}</a>
        <a href="{{ url('/portal/login') }}" class="btn btn-outline-light">{{ t('support.cta_portal') }}</a>
      </div>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal-stagger" style="max-width:960px;margin:0 auto 40px;">
      <div class="card feature-card reveal">
        <div class="icon">🎫</div>
        <h3>{{ t('support.card1.title') }}</h3>
        <p>{{ t('support.card1.desc') }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">🧑‍🤝‍🧑</div>
        <h3>{{ t('support.card2.title') }}</h3>
        <p>{{ t('support.card2.desc') }}</p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">⭐</div>
        <h3>{{ t('support.card3.title') }}</h3>
        <p>{{ t('support.card3.desc') }}</p>
      </div>
    </div>

    <div class="section-head">
      <div class="eyebrow">{{ t('support.faq_eyebrow') }}</div>
      <h2>{{ t('support.faq_title') }}</h2>
    </div>
    <div class="faq-list reveal-section reveal">
      @foreach ([1,2,3,4] as $i)
        <details class="faq-item" @if($i === 1) open @endif>
          <summary>{{ t("support.faq{$i}.q") }}</summary>
          <div class="answer">{{ t("support.faq{$i}.a") }}</div>
        </details>
      @endforeach
    </div>

    <div class="reveal-section reveal" style="text-align:center;margin-top:36px;">
      <p style="color:var(--muted);">{{ t('support.still_need_help') }}</p>
      <a href="{{ url('/contact') }}" class="btn btn-outline">{{ t('support.contact_us') }}</a>
    </div>
  </div>
</section>
@include('partials.custom-sections', ['page' => 'support'])
@endsection
