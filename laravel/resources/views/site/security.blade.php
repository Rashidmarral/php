@extends('layouts.site')

@php($siteName = \App\Models\Setting::siteName())

@section('content')
<section class="hero" style="padding-bottom:40px;">
  @include('partials.hero-media', ['page' => 'security'])
  <div class="container" style="grid-template-columns:1fr;max-width:760px;text-align:center;">
    <div>
      <span class="hero-badge">🔒 {{ t('security.badge') }}</span>
      <h1>{{ t('security.title') }}</h1>
      <p class="lead" style="max-width:680px;margin-left:auto;margin-right:auto;">{{ t('security.lead', ['site' => $siteName]) }}</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal-stagger" style="max-width:1000px;margin:0 auto;">
      @foreach ([
        ['icon' => '🧾', 'key' => 'card1'],
        ['icon' => '🔐', 'key' => 'card2'],
        ['icon' => '🔑', 'key' => 'card3'],
        ['icon' => '🔒', 'key' => 'card4'],
        ['icon' => '✍️', 'key' => 'card5'],
        ['icon' => '💾', 'key' => 'card6'],
      ] as $c)
        <div class="card feature-card reveal">
          <div class="icon">{{ $c['icon'] }}</div>
          <h3>{{ t("security.{$c['key']}.title") }}</h3>
          <p>{{ t("security.{$c['key']}.desc") }}</p>
        </div>
      @endforeach
    </div>

    <div class="reveal-section reveal" style="text-align:center;margin-top:44px;">
      <p style="color:var(--muted);">{{ t('security.question') }}</p>
      <a href="{{ url('/contact') }}" class="btn btn-outline">{{ t('security.talk_to_team') }}</a>
    </div>
  </div>
</section>
@endsection
