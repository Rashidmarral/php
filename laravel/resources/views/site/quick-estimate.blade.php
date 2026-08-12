@extends('layouts.site')

@section('content')
<section class="qe-hero">
  <div class="container">
    <h1>📄 {{ t('qe.title') }}</h1>
    <p>{{ t('qe.subtitle') }}</p>
    <div class="qe-badges">
      <span class="qe-badge">⚡ {{ t('qe.instant') }}</span>
      <span class="qe-badge">📑 {{ t('qe.pdf_export') }}</span>
      <span class="qe-badge">✅ {{ t('qe.vat_compliant') }}</span>
    </div>
  </div>
</section>

<div class="container">
  <form method="post" action="{{ url('/quick-estimate') }}" id="qe-form">
    @csrf
    <div class="qe-layout">

      <!-- Sticky summary sidebar -->
      <div class="card qe-summary">
        <h3 style="font-size:14px;">{{ t('qe.summary') }}</h3>
        <div class="mini-grid">
          <div class="mini-kpi"><div class="l">{{ t('qe.addons') }}</div><div class="v" id="qe-addons-total">0.00</div></div>
          <div class="mini-kpi"><div class="l">{{ t('qe.foundation') }}</div><div class="v" id="qe-foundation-total">0.00</div></div>
        </div>
        <div class="mini-grid">
          <div class="mini-kpi"><div class="l">{{ t('qe.region') }}</div><div class="v" id="qe-region-mult">1.00x</div></div>
          <div class="mini-kpi"><div class="l">{{ t('qe.discount') }}</div><div class="v" id="qe-discount-pct">0%</div></div>
        </div>

        <div class="total-box">
          <div class="label">{{ t('qe.total') }}</div>
          <div class="value"><span id="qe-total">0.00</span> SAR</div>
        </div>

        <div class="breakdown">
          <div><span>{{ t('qe.subtotal') }}</span><span id="qe-subtotal">0.00</span></div>
          <div><span>{{ t('qe.discount') }}</span><span id="qe-discount-amt">-0.00</span></div>
          <div><span id="qe-vat-label">{{ t('qe.vat', ['rate' => $vatRate]) }}</span><span id="qe-vat-amt">0.00</span></div>
        </div>
        <div class="breakdown" style="margin-top:8px;">
          <div><strong>{{ t('qe.per_sqm') }}</strong></div>
          <div><span id="qe-per-sqm">0.00</span> SAR/m²</div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">✉ {{ t('qe.generate') }}</button>
        <p class="help-text" style="text-align:center;margin-top:8px;">🔒 {{ t('qe.secure_note') }}</p>
      </div>

      <!-- Main form -->
      <div>
        <div class="card" style="margin-bottom:18px;">
          <h3>{{ t('qe.project_details') }}</h3>
          <div class="form-group">
            <label>{{ t('qe.project_name') }}</label>
            <input type="text" name="project_name" placeholder="{{ t('qe.project_name') }}">
          </div>
        </div>

        <div class="card" style="margin-bottom:18px;">
          <h3>📍 {{ t('qe.region') }}</h3>
          <div class="qe-pick-grid" id="qe-regions">
            @foreach ($regions as $r)
              @php($name = app()->getLocale() === 'ar' ? $r['name_ar'] : $r['name_en'])
              <label class="qe-pick-card" data-id="{{ $r['id'] }}" data-price="{{ $r['price_per_sqm'] }}" data-mult="{{ $r['multiplier'] }}">
                <input type="radio" name="region_id" value="{{ $r['id'] }}">
                <div class="name">{{ $name }}</div>
                <div class="price">SAR {{ number_format((float)$r['price_per_sqm'],2) }}</div>
              </label>
            @endforeach
          </div>

          <h3>🧱 {{ t('qe.foundation_type') }}</h3>
          <div class="qe-pick-grid" id="qe-foundations" style="grid-template-columns:repeat(2,1fr);">
            @foreach ($foundations as $f)
              @php($name = app()->getLocale() === 'ar' ? $f['name_ar'] : $f['name_en'])
              @php($desc = app()->getLocale() === 'ar' ? $f['description_ar'] : $f['description_en'])
              <label class="qe-pick-card" data-id="{{ $f['id'] }}" data-price="{{ $f['price_per_sqm'] }}">
                <input type="radio" name="foundation_id" value="{{ $f['id'] }}">
                <div class="name">{{ $name }}</div>
                <div class="desc">{{ $desc }}</div>
                <div class="price">SAR/m² {{ number_format((float)$f['price_per_sqm'],2) }}</div>
              </label>
            @endforeach
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>{{ t('qe.discount') }} (%)</label>
              <input type="number" name="discount_percent" id="qe-discount-input" min="0" max="100" value="0">
            </div>
            <div class="form-group">
              <label>{{ t('qe.total_area') }} (m²)</label>
              <input type="number" name="total_area" id="qe-area-input" min="0" value="0" required>
            </div>
          </div>
        </div>

        <div class="card" style="margin-bottom:18px;">
          <h3>🔧 {{ t('qe.addons') }}</h3>
          <p class="help-text" style="margin-top:-8px;">{{ t('qe.addons_hint') }}</p>
          <div class="qe-addon-grid" id="qe-addons">
            @foreach ($addons as $a)
              @php($name = app()->getLocale() === 'ar' ? $a['name_ar'] : $a['name_en'])
              @php($desc = app()->getLocale() === 'ar' ? $a['description_ar'] : $a['description_en'])
              <label class="qe-addon-card" data-id="{{ $a['id'] }}" data-price="{{ $a['unit_price'] }}">
                <div>
                  <div class="name">{{ $name }}@if($a['is_pro'])<span class="qe-pro-tag">PRO</span>@endif</div>
                  <div class="desc">{{ $desc }}</div>
                  <div class="price">SAR/{{ $a['unit_type'] }} {{ number_format((float)$a['unit_price'],2) }}</div>
                </div>
                <input type="checkbox" name="addons[]" value="{{ $a['id'] }}">
              </label>
            @endforeach
          </div>
        </div>

        <div class="card" style="margin-bottom:18px;">
          <div class="form-row">
            <div class="form-group"><label>{{ t('qe.your_name') }}</label><input type="text" name="contact_name"></div>
            <div class="form-group"><label>{{ t('auth.email') }}</label><input type="email" name="contact_email"></div>
          </div>
          <div class="form-group"><label>{{ t('auth.phone') }}</label><input type="tel" name="contact_phone" placeholder="+966 5x xxx xxxx"></div>
          <div class="form-group"><label>{{ t('qe.notes') }}</label><textarea name="notes" placeholder="{{ t('qe.notes_placeholder') }}"></textarea></div>
        </div>

        <button type="submit" class="btn btn-primary">✉ {{ t('qe.generate') }}</button>
      </div>
    </div>
  </form>
</div>

<script>
(function() {
  const vatRate = {!! json_encode($vatRate) !!};
  const areaInput = document.getElementById('qe-area-input');
  const discountInput = document.getElementById('qe-discount-input');
  let selectedRegion = null;
  let selectedFoundation = null;
  const selectedAddons = new Map();

  function selectCard(container, card) {
    container.querySelectorAll('.qe-pick-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input').checked = true;
  }

  document.querySelectorAll('#qe-regions .qe-pick-card').forEach(card => {
    card.addEventListener('click', () => {
      selectCard(document.getElementById('qe-regions'), card);
      selectedRegion = { price: parseFloat(card.dataset.price), mult: parseFloat(card.dataset.mult) };
      recalc();
    });
  });

  document.querySelectorAll('#qe-foundations .qe-pick-card').forEach(card => {
    card.addEventListener('click', () => {
      selectCard(document.getElementById('qe-foundations'), card);
      selectedFoundation = { price: parseFloat(card.dataset.price) };
      recalc();
    });
  });

  document.querySelectorAll('#qe-addons .qe-addon-card').forEach(card => {
    const checkbox = card.querySelector('input');
    checkbox.addEventListener('change', () => {
      card.classList.toggle('selected', checkbox.checked);
      if (checkbox.checked) {
        selectedAddons.set(card.dataset.id, parseFloat(card.dataset.price));
      } else {
        selectedAddons.delete(card.dataset.id);
      }
      recalc();
    });
  });

  areaInput.addEventListener('input', recalc);
  discountInput.addEventListener('input', recalc);

  function recalc() {
    const area = parseFloat(areaInput.value) || 0;
    const discountPct = Math.min(100, Math.max(0, parseFloat(discountInput.value) || 0));

    const base = selectedRegion ? area * selectedRegion.price : 0;
    const foundation = selectedFoundation ? area * selectedFoundation.price : 0;
    let addonsTotal = 0;
    selectedAddons.forEach(price => addonsTotal += price * area);

    const mult = selectedRegion ? selectedRegion.mult : 1;
    const subtotal = (base + foundation + addonsTotal) * mult;
    const discountAmt = subtotal * discountPct / 100;
    const taxable = subtotal - discountAmt;
    const vatAmt = taxable * vatRate / 100;
    const total = taxable + vatAmt;
    const perSqm = area > 0 ? total / area : 0;

    document.getElementById('qe-addons-total').textContent = addonsTotal.toFixed(2);
    document.getElementById('qe-foundation-total').textContent = foundation.toFixed(2);
    document.getElementById('qe-region-mult').textContent = mult.toFixed(2) + 'x';
    document.getElementById('qe-discount-pct').textContent = discountPct + '%';
    document.getElementById('qe-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('qe-discount-amt').textContent = '-' + discountAmt.toFixed(2);
    document.getElementById('qe-vat-amt').textContent = vatAmt.toFixed(2);
    document.getElementById('qe-total').textContent = total.toFixed(2);
    document.getElementById('qe-per-sqm').textContent = perSqm.toFixed(2);
  }

  recalc();
})();
</script>
@endsection
