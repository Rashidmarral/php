@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">

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

    <div class="card" style="padding:32px;">
      <div style="display:flex;gap:14px;align-items:center;">
        @if(!empty($companyLogoUrl))
          <img src="{{ $companyLogoUrl }}" alt="{{ $company->name ?? '' }}" style="height:48px;width:auto;border-radius:6px;">
        @endif
        <div>
          <div class="eyebrow">{{ t('site.share.rfq_bid_eyebrow', ['company' => $company->name ?? t('site.share.the_company')]) }}</div>
          <h1 style="margin-top:4px;">{{ $rfq['title'] }}</h1>
          @if(!empty($rfq['due_date']))
            <p class="help-text">{{ t('site.share.rfq_bid_due_date', ['date' => $rfq['due_date']]) }}</p>
          @endif
        </div>
      </div>

      @if(count($items))
        <table class="data" style="margin-top:24px;">
          <thead><tr><th>{{ t('common.description') }}</th><th>{{ t('common.qty') }}</th><th>{{ t('common.unit') }}</th></tr></thead>
          <tbody>
            @foreach ($items as $it)
              <tr><td>{{ $it['description'] }}</td><td>{{ $it['qty'] }}</td><td>{{ $it['unit'] ?: '—' }}</td></tr>
            @endforeach
          </tbody>
        </table>
      @endif
    </div>

    @if($quote['submitted_at'])
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">✅</div>
        <h3>{{ t('site.share.rfq_bid_already_submitted_heading') }}</h3>
        <p class="help-text">{{ t('site.share.rfq_bid_already_submitted_hint') }}</p>
        <div class="breakdown" style="margin-top:14px;max-width:320px;margin-inline:auto;font-size:14px;text-align:start;">
          <div><span>{{ t('site.share.rfq_bid_your_total') }}</span><span>{!! money((float) $quote['total_amount']) !!}</span></div>
          <div><span>{{ t('site.share.rfq_bid_your_lead_time') }}</span><span>{{ $quote['lead_time_days'] !== null ? $quote['lead_time_days'] : '—' }}</span></div>
          @if(!empty($quote['notes']))
            <div><span>{{ t('site.share.rfq_bid_your_notes') }}</span><span>{{ $quote['notes'] }}</span></div>
          @endif
        </div>
      </div>
    @else
      <div class="card" style="margin-top:20px;padding:32px;">
        <h3>{{ t('site.share.rfq_bid_form_heading') }}</h3>
        <p class="help-text">{{ t('site.share.rfq_bid_form_hint') }}</p>

        <form method="post" action="{{ url('/rfq-bid/' . $token . '/submit') }}">
          @csrf
          <div class="form-row">
            <div class="form-group">
              <label>{{ t('site.share.rfq_bid_total_label') }}</label>
              <input type="number" step="0.01" min="0.01" name="total_amount" required>
            </div>
            <div class="form-group">
              <label>{{ t('site.share.rfq_bid_lead_time_label') }}</label>
              <input type="number" step="1" min="0" name="lead_time_days">
            </div>
          </div>
          <div class="form-group">
            <label>{{ t('site.share.rfq_bid_notes_label') }}</label>
            <textarea name="notes" rows="3"></textarea>
          </div>
          <button type="submit" class="btn btn-primary">{{ t('site.share.rfq_bid_submit_btn') }}</button>
        </form>
      </div>
    @endif
  </div>
</section>
@endsection
