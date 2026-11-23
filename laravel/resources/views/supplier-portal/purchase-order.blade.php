@extends('layouts.supplier-portal')

@section('content')
<div class="page-head">
  <h1>{{ $purchaseOrder->po_number }}</h1>
  <a href="{{ url('/supplier-portal') }}" class="btn btn-light">&larr; {{ t('supplier_portal.back') }}</a>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label">{{ t('common.status') }}</div><div class="value" style="font-size:16px;"><span class="badge badge-blue">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span></div></div>
  <div class="kpi"><div class="label">{{ t('supplier_portal.issue_date_col') }}</div><div class="value" style="font-size:16px;">{{ $purchaseOrder->issue_date ?: '—' }}</div></div>
  <div class="kpi"><div class="label">{{ t('supplier_portal.expected_delivery_col') }}</div><div class="value" style="font-size:16px;">{{ $purchaseOrder->expected_delivery_date ?: '—' }}</div></div>
  <div class="kpi"><div class="label">{{ t('common.total') }}</div><div class="value" style="font-size:16px;">{!! money((float) $purchaseOrder->total) !!}</div></div>
</div>

<div class="card" style="margin-bottom:20px;">
  @if ($purchaseOrder->acknowledged_at)
    <p class="help-text" style="margin:0;">{{ t('supplier_portal.acknowledged_on', ['date' => $purchaseOrder->acknowledged_at->format('Y-m-d H:i')]) }}</p>
  @else
    <form method="post" action="{{ url('/supplier-portal/purchase-orders/' . $purchaseOrder->id . '/acknowledge') }}">
      @csrf
      <p class="help-text" style="margin-top:0;">{{ t('supplier_portal.acknowledge_hint') }}</p>
      <button type="submit" class="btn btn-primary">{{ t('supplier_portal.acknowledge_btn') }}</button>
    </form>
  @endif
</div>

<div class="card">
  <h3>{{ t('supplier_portal.line_items_title') }}</h3>
  <table class="data">
    <thead><tr><th>{{ t('common.description') }}</th><th>{{ t('supplier_portal.qty_col') }}</th><th>{{ t('supplier_portal.unit_price_col') }}</th><th>{{ t('common.total') }}</th></tr></thead>
    <tbody>
    @foreach ($items as $item)
      <tr>
        <td>{{ $item->description }}</td>
        <td>{{ $item->qty }}</td>
        <td>{!! money((float) $item->unit_price) !!}</td>
        <td>{!! money((float) $item->total) !!}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
