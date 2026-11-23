@extends('layouts.supplier-portal')

@section('content')
<div class="page-head">
  <h1>{{ $subcontract->title }}</h1>
  <a href="{{ url('/supplier-portal') }}" class="btn btn-light">&larr; {{ t('supplier_portal.back') }}</a>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label">{{ t('common.status') }}</div><div class="value" style="font-size:16px;"><span class="badge badge-blue">{{ str_replace('_', ' ', $subcontract->status) }}</span></div></div>
  <div class="kpi"><div class="label">{{ t('supplier_portal.contract_value_col') }}</div><div class="value" style="font-size:16px;">{!! money((float) $subcontract->contract_value) !!}</div></div>
  <div class="kpi"><div class="label">{{ t('supplier_portal.cumulative_paid_col') }}</div><div class="value" style="font-size:16px;">{!! money($cumulativePaid) !!}</div></div>
  <div class="kpi"><div class="label">{{ t('supplier_portal.retention_held_col') }}</div><div class="value" style="font-size:16px;">{!! money($retentionHeld) !!}</div></div>
</div>

@if ($subcontract->description)
  <div class="card" style="margin-bottom:20px;"><p>{{ $subcontract->description }}</p></div>
@endif

<div class="card">
  <h3>{{ t('supplier_portal.payment_history_title') }}</h3>
  @if ($payments->isEmpty())
    <p class="help-text">{{ t('supplier_portal.no_payments_yet') }}</p>
  @else
    <table class="data">
      <thead><tr><th>#</th><th>{{ t('common.date') }}</th><th>{{ t('common.status') }}</th><th>{{ t('supplier_portal.cumulative_value_col') }}</th><th>{{ t('supplier_portal.net_payable_col') }}</th></tr></thead>
      <tbody>
      @foreach ($payments as $p)
        <tr>
          <td>{{ $p->payment_number }}</td>
          <td class="help-text">{{ $p->payment_date }}</td>
          <td><span class="badge badge-{{ $p->status === 'certified' ? 'green' : 'gray' }}">{{ ucfirst($p->status) }}</span></td>
          <td>{!! money((float) $p->cumulative_value) !!}</td>
          <td>{!! money((float) $p->net_payable) !!}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
