@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;padding-bottom:80px;">
  <div class="container" style="max-width:520px;">

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

    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-bottom:4px;">Pay invoice {{ $invoice->invoice_number }}</h3>
      <p class="help-text" style="margin-bottom:14px;">To {{ $company->name ?? '' }}</p>
      <div class="total-row">{!! money((float) $invoice->total) !!}</div>
    </div>

    <div class="tabs" id="method-tabs">
      <a href="#card" class="tab-link active" data-tab="card"><?= t('site.share.card_option') ?></a>
      <a href="#stcpay" class="tab-link" data-tab="stcpay"><?= t('site.share.stc_pay_option') ?></a>
    </div>

    <div id="tab-card" class="tab-panel">
      <div class="card">
        <p class="help-text" style="margin-bottom:14px;">Your card details are handled directly by Moyasar — they never pass through {{ $company->name ?? 'our' }}'s servers or {{ \App\Models\Setting::siteName() }}'s.</p>
        <div class="mysr-form"
          data-amount="{{ (int) round((float) $invoice->total * 100) }}"
          data-currency="SAR"
          data-description="Invoice {{ $invoice->invoice_number }}"
          data-publishable-api-key="{{ $moyasarPublishableKey }}"
          data-callback-url="{{ '/i/' . $token . '/pay/callback' }}"
          data-methods="creditcard,applepay,stcpay">
        </div>
      </div>
    </div>

    <div id="tab-stcpay" class="tab-panel" style="display:none;">
      <div class="card">
        <p class="help-text" style="margin-bottom:14px;"><?= t('site.share.stc_pay_hint') ?></p>
        <form method="post" action="{{ url('/i/' . $token . '/pay/stc-pay') }}">
          <?= csrf_field() ?>
          <div class="form-group">
            <label><?= t('site.share.stc_pay_mobile_label') ?></label>
            <input type="tel" name="stc_pay_mobile" placeholder="05XXXXXXXX" required>
          </div>
          <button type="submit" class="btn btn-primary btn-block"><?= t('site.share.stc_pay_submit') ?></button>
        </form>
      </div>
    </div>

    <p style="text-align:center;margin-top:16px;"><a href="{{ url('/i/' . $token) }}">← Back to invoice</a></p>
  </div>
</section>
<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
<script src="https://cdn.moyasar.com/mpf/1.15.0/moyasar.js"></script>
<script>
document.querySelectorAll('#method-tabs .tab-link').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('#method-tabs .tab-link').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    link.classList.add('active');
    document.getElementById('tab-' + link.dataset.tab).style.display = 'block';
  });
});
</script>
@endsection
