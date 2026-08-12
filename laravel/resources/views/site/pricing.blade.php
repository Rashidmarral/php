@extends('layouts.site')

@php($featured = 'professional')

@section('content')
<section class="section" style="padding-top:56px;">
  <div class="container">
    <div class="section-head">
      <div class="eyebrow">{{ t('pricing.eyebrow') }}</div>
      <h1>{{ t('pricing.title') }}</h1>
      <p style="color:var(--muted)">{{ t('pricing.subtitle') }}</p>
    </div>

    <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:32px;">
      <span id="cycle-label-monthly" style="font-weight:700;">{{ t('pricing.monthly') }}</span>
      <label class="cycle-switch">
        <input type="checkbox" id="cycle-toggle">
        <span class="cycle-slider"></span>
      </label>
      <span id="cycle-label-yearly" style="color:var(--muted);">{{ t('pricing.yearly') }}</span>
    </div>

    <div class="grid grid-3">
      @foreach ($plans as $plan)
        @php($features = json_decode($plan['features'], true) ?: [])
        <div class="card pricing-card {{ $plan['slug'] === $featured ? 'featured' : '' }}">
          @if($plan['slug'] === $featured)<span class="badge-featured">{{ t('pricing.most_popular') }}</span>@endif
          <h3>{{ (app()->getLocale() === 'ar' && !empty($plan['name_ar'])) ? $plan['name_ar'] : $plan['name'] }}</h3>
          <p style="color:var(--muted);font-size:13.5px;min-height:36px;">{{ (app()->getLocale() === 'ar' && !empty($plan['tagline_ar'])) ? $plan['tagline_ar'] : $plan['tagline'] }}</p>
          <div class="price">
            <span class="price-amount"
              data-monthly="{{ number_format((float)$plan['price_monthly'], 0) }}"
              data-yearly="{{ number_format((float)$plan['price_yearly'] / 12, 0) }}"
            >{{ number_format((float)$plan['price_monthly'], 0) }}</span>
            <small>SAR {{ t('pricing.per_month') }}</small>
          </div>
          <p class="help-text price-yearly-note" style="display:none;">{{ number_format((float)$plan['price_yearly'], 0) }} SAR {{ t('pricing.per_year') }} — 2 months free</p>
          <p class="help-text price-monthly-note">{{ number_format((float)$plan['price_yearly'], 0) }} SAR {{ t('pricing.per_year') }} {{ t('pricing.if_billed_yearly') }}</p>
          <p class="help-text">
            {{ $plan['max_users'] >= 999 ? '∞' : $plan['max_users'] }} {{ t('pricing.users') }} ·
            {{ $plan['max_projects'] >= 999 ? '∞' : $plan['max_projects'] }} {{ t('pricing.projects') }}
          </p>
          <ul class="plan-features">
            @foreach ($features as $f)<li>{{ $f }}</li>@endforeach
          </ul>
          <a href="{{ url('/register') }}?plan={{ urlencode($plan['slug']) }}" class="btn {{ $plan['slug'] === $featured ? 'btn-primary' : 'btn-outline' }} btn-block plan-cta" data-slug="{{ urlencode($plan['slug']) }}">{{ t('pricing.cta') }}</a>
        </div>
      @endforeach
    </div>

    <script>
    (function() {
      const toggle = document.getElementById('cycle-toggle');
      const amounts = document.querySelectorAll('.price-amount');
      const monthlyLabel = document.getElementById('cycle-label-monthly');
      const yearlyLabel = document.getElementById('cycle-label-yearly');
      const monthlyNotes = document.querySelectorAll('.price-monthly-note');
      const yearlyNotes = document.querySelectorAll('.price-yearly-note');
      const ctas = document.querySelectorAll('.plan-cta');

      toggle.addEventListener('change', () => {
        const yearly = toggle.checked;
        amounts.forEach(el => { el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly; });
        monthlyNotes.forEach(el => { el.style.display = yearly ? 'none' : ''; });
        yearlyNotes.forEach(el => { el.style.display = yearly ? '' : 'none'; });
        monthlyLabel.style.fontWeight = yearly ? '400' : '700';
        monthlyLabel.style.color = yearly ? 'var(--muted)' : '';
        yearlyLabel.style.fontWeight = yearly ? '700' : '400';
        yearlyLabel.style.color = yearly ? '' : 'var(--muted)';
        ctas.forEach(a => { a.href = '/register?plan=' + a.dataset.slug + '&cycle=' + (yearly ? 'yearly' : 'monthly'); });
      });
    })();
    </script>

    <p style="text-align:center;color:var(--muted);margin-top:36px;font-size:13.5px;">
      All plans include a 14-day free trial. Prices exclude 15% Saudi VAT. Need a custom plan for a large enterprise? <a href="{{ url('/contact') }}">Talk to sales</a>.
    </p>

    <div class="section-head" style="margin-top:56px;">
      <div class="eyebrow">{{ t('pricing.compare_eyebrow') }}</div>
      <h2>{{ t('pricing.compare_title') }}</h2>
    </div>
    <div class="compare-table-wrap">
      <table class="compare-table">
        <thead>
          <tr>
            <th>{{ t('pricing.compare_feature') }}</th>
            @foreach ($plans as $plan)
              <th>{{ (app()->getLocale() === 'ar' && !empty($plan['name_ar'])) ? $plan['name_ar'] : $plan['name'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ t('pricing.users') }}</td>
            @foreach ($plans as $plan)<td>{{ $plan['max_users'] >= 999 ? '∞' : $plan['max_users'] }}</td>@endforeach
          </tr>
          <tr>
            <td>{{ t('pricing.projects') }}</td>
            @foreach ($plans as $plan)<td>{{ $plan['max_projects'] >= 999 ? '∞' : $plan['max_projects'] }}</td>@endforeach
          </tr>
          @foreach ($allFeatures as $key => $label)
            <tr>
              <td>{{ $label }}</td>
              @foreach ($plans as $plan)
                @php($flags = json_decode($plan['feature_flags'], true) ?: [])
                <td>@if(!empty($flags[$key]))<span class="yes">✓</span>@else<span class="no">—</span>@endif</td>
              @endforeach
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</section>
@endsection
