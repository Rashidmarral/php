@extends('layouts.site')

@section('content')
<section class="hero" style="padding-bottom:40px;">
  <div class="container" style="grid-template-columns:1fr;max-width:720px;text-align:center;">
    <div>
      <span class="hero-badge">✉️ {{ t('contact.eyebrow') }}</span>
      <h1>{{ t('contact.title') }}</h1>
      <p class="lead" style="max-width:640px;margin-left:auto;margin-right:auto;">{{ t('contact.lead') }}</p>
    </div>
  </div>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="grid grid-3 reveal-stagger" style="max-width:920px;margin:0 auto 40px;">
      <div class="card feature-card reveal">
        <div class="icon">📧</div>
        <h3>{{ t('contact.email.label') }}</h3>
        <p>{{ t('contact.email.desc') }}</p>
        <p style="margin-top:8px;"><bdi><a href="mailto:hello@buildxact-saudi.com">hello@buildxact-saudi.com</a></bdi></p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">📱</div>
        <h3>{{ t('contact.phone.label') }}</h3>
        <p>{{ t('contact.phone.desc') }}</p>
        <p style="margin-top:8px;"><bdi>+966 11 000 0000</bdi></p>
      </div>
      <div class="card feature-card reveal">
        <div class="icon">📍</div>
        <h3>{{ t('contact.office.label') }}</h3>
        <p>{{ t('contact.office.desc') }}</p>
      </div>
    </div>

    <div class="grid grid-2" style="max-width:920px;margin:0 auto;align-items:start;gap:32px;">
      <div>
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
        <?php session()->forget(['flash.error', 'flash.success']); ?>

        <form method="post" action="{{ url('/contact') }}" class="card reveal-section reveal">
          <h3 style="font-size:14px;margin-bottom:4px;">{{ t('contact.form.title') }}</h3>
          @csrf
          <div class="form-row">
            <div class="form-group"><label>Full name</label><input type="text" name="name" required></div>
            <div class="form-group"><label>Company</label><input type="text" name="company"></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
            <div class="form-group"><label>Phone</label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx"></div>
          </div>
          <div class="form-group"><label>Message</label><textarea name="message" required></textarea></div>
          <button type="submit" class="btn btn-primary btn-block">Send message</button>
        </form>
      </div>

      <div>
        <div class="card reveal-section reveal">
          <div class="eyebrow">{{ t('contact.faq.eyebrow') }}</div>
          <h3 style="margin-top:4px;">{{ t('contact.faq.title') }}</h3>
          <div class="faq-list" style="margin-top:8px;">
            @foreach ([1,2,3] as $i)
              <details class="faq-item">
                <summary>{{ t("contact.faq{$i}.q") }}</summary>
                <div class="answer">{{ t("contact.faq{$i}.a") }}</div>
              </details>
            @endforeach
          </div>
        </div>
      </div>
    </div>

    <div class="reveal-section reveal" style="max-width:920px;margin:32px auto 0;">
      <div class="section-head" style="text-align:start;margin-bottom:16px;">
        <div class="eyebrow">{{ t('contact.map.eyebrow') }}</div>
        <h3 style="margin-top:4px;">{{ t('contact.map.title') }}</h3>
      </div>
      <div class="map-embed">
        <iframe
          src="https://maps.google.com/maps?q=Riyadh%2C%20Saudi%20Arabia&t=&z=11&ie=UTF8&iwloc=&output=embed"
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="{{ \App\Models\Setting::siteName() }} office location"
        ></iframe>
      </div>
    </div>
  </div>
</section>
@endsection
