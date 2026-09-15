@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;padding-bottom:80px;">
  <div class="container" style="max-width:520px;">
    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-bottom:4px;">Pay invoice {{ $invoice->invoice_number }}</h3>
      <p class="help-text" style="margin-bottom:14px;">To {{ $company->name ?? '' }}</p>
      <div class="total-row">{!! money((float) $invoice->total) !!}</div>
    </div>

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
    <p style="text-align:center;margin-top:16px;"><a href="{{ url('/i/' . $token) }}">← Back to invoice</a></p>
  </div>
</section>
<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
<script src="https://cdn.moyasar.com/mpf/1.15.0/moyasar.js"></script>
@endsection
