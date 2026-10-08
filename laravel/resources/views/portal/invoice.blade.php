@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>{{ $invoice->invoice_number }}</h1>
  <a href="{{ url('/portal') }}" class="btn btn-light">← Back</a>
</div>

<div class="card" style="max-width:820px;">
  <span class="badge badge-{{ ['paid'=>'green','overdue'=>'red'][$invoice->status] ?? 'yellow' }}" style="margin-bottom:14px;display:inline-block;">{{ $invoice->status }}</span>
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
    <tbody>
      @foreach ($items as $it)
        <tr><td>{{ $it->description }}</td><td>{{ $it->qty }}</td><td>{!! money((float)$it->unit_price) !!}</td><td>{!! money((float)$it->total) !!}</td></tr>
      @endforeach
    </tbody>
  </table>
  <div class="total-row" style="margin-top:14px;">Total: {!! money((float)$invoice->total) !!}</div>
  @if($invoice->due_date)<p class="help-text">Due: {{ $invoice->due_date }}</p>@endif
  @if($canPayOnline)
    <a href="{{ url('/i/' . $invoice->share_token . '/pay') }}" class="btn btn-primary" style="margin-top:16px;">💳 Pay now</a>
  @endif
</div>
@endsection
