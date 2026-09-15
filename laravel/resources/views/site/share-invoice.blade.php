@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">
    <div class="card" style="padding:32px;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div>
          <div class="eyebrow">Invoice from {{ $company['name'] ?? '' }}</div>
          <h1 style="margin-top:4px;">{{ $invoice['invoice_number'] }}</h1>
          <p class="help-text">Billed to {{ $client['name'] ?? 'you' }} · {{ $invoice['created_at'] }}</p>
        </div>
        <span class="badge badge-{{ ['paid'=>'green','overdue'=>'red'][$invoice['status']] ?? 'yellow' }}" style="font-size:13px;padding:6px 14px;">{{ ucfirst($invoice['status']) }}</span>
      </div>

      <table class="data" style="margin-top:24px;">
        <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
        <tbody>
          @foreach ($items as $it)
            <tr><td>{{ $it['description'] }}</td><td>{{ $it['qty'] }}</td><td>{!! money((float)$it['unit_price']) !!}</td><td>{!! money((float)$it['total']) !!}</td></tr>
          @endforeach
        </tbody>
      </table>
      @if(!empty($invoice['vat_amount']))
        @php($subtotal = (float)$invoice['total'] - (float)$invoice['vat_amount'])
        <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:14px;">
          Subtotal: {!! money($subtotal) !!}<br>
          VAT ({{ (string)$invoice['vat_rate'] }}%): {!! money((float)$invoice['vat_amount']) !!}
        </div>
      @endif
      <div class="total-row" style="margin-top:6px;">Total: {!! money((float)$invoice['total']) !!}</div>
      @if($invoice['due_date'])<p class="help-text">Due: {{ $invoice['due_date'] }}</p>@endif

      <div style="display:flex;gap:8px;margin-top:16px;">
        <a href="{{ url('/i/' . $invoice['share_token'] . '/pdf') }}" target="_blank" class="btn btn-outline">⬇ Download PDF</a>
        @if($invoice['status'] !== 'paid' && \App\Support\Moyasar::isConfiguredForCompany($company))
          <a href="{{ url('/i/' . $invoice['share_token'] . '/pay') }}" class="btn btn-primary">💳 Pay now</a>
        @endif
      </div>
    </div>

    @if($zatcaQr)
      <div class="card" style="margin-top:20px;padding:24px;display:flex;gap:16px;align-items:center;">
        <img src="{{ $zatcaQr }}" width="90" height="90" alt="ZATCA QR Code">
        <div>
          <h3 style="margin-bottom:4px;font-size:15px;">ZATCA QR Code</h3>
          <p class="help-text">Scan to verify this invoice's seller, VAT number, timestamp, and total.</p>
        </div>
      </div>
    @endif
  </div>
</section>
@endsection
