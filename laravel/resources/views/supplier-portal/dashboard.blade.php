@extends('layouts.supplier-portal')

@section('content')
<div class="page-head">
  <h1>{{ t('supplier_portal.welcome', ['name' => $supplier->name]) }}</h1>
  <a href="{{ url('/supplier-portal/bills/create') }}" class="btn btn-primary">{{ t('supplier_portal.submit_bill_nav') }}</a>
</div>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3>{{ t('supplier_portal.purchase_orders_title') }}</h3>
    @if ($purchaseOrders->isEmpty())
      <p class="help-text">{{ t('supplier_portal.no_purchase_orders') }}</p>
    @else
      <table class="data">
        <thead><tr><th>{{ t('supplier_portal.po_number_col') }}</th><th>{{ t('common.status') }}</th><th>{{ t('common.total') }}</th><th>{{ t('supplier_portal.acknowledged_col') }}</th></tr></thead>
        <tbody>
        @foreach ($purchaseOrders as $po)
          <tr>
            <td><a href="{{ url('/supplier-portal/purchase-orders/' . $po->id) }}">{{ $po->po_number }}</a></td>
            <td><span class="badge badge-blue">{{ str_replace('_', ' ', $po->status) }}</span></td>
            <td>{!! money((float) $po->total) !!}</td>
            <td>{!! $po->acknowledged_at ? '<span class="badge badge-green">' . t('supplier_portal.acknowledged') . '</span>' : '<span class="badge badge-gray">' . t('supplier_portal.not_acknowledged') . '</span>' !!}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h3>{{ t('supplier_portal.subcontracts_title') }}</h3>
    @if ($subcontracts->isEmpty())
      <p class="help-text">{{ t('supplier_portal.no_subcontracts') }}</p>
    @else
      <table class="data">
        <thead><tr><th>{{ t('common.title') }}</th><th>{{ t('supplier_portal.contract_value_col') }}</th><th>{{ t('supplier_portal.cumulative_paid_col') }}</th></tr></thead>
        <tbody>
        @foreach ($subcontracts as $sc)
          <tr>
            <td><a href="{{ url('/supplier-portal/subcontracts/' . $sc->id) }}">{{ $sc->title }}</a></td>
            <td>{!! money((float) $sc->contract_value) !!}</td>
            <td>{!! money($sc->cumulativePaid()) !!}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3>{{ t('supplier_portal.submissions_title') }}</h3>
  @if ($submissions->isEmpty())
    <p class="help-text">{{ t('supplier_portal.no_submissions') }}</p>
  @else
    <table class="data">
      <thead><tr><th>{{ t('common.date') }}</th><th>{{ t('common.description') }}</th><th>{{ t('common.amount') }}</th><th>{{ t('common.status') }}</th></tr></thead>
      <tbody>
      @foreach ($submissions as $sub)
        <tr>
          <td class="help-text">{{ $sub->bill_date }}</td>
          <td>{{ $sub->description }}</td>
          <td>{!! money((float) $sub->amount) !!}</td>
          <td>
            @php
              $statusBadge = ['pending' => 'yellow', 'approved' => 'green', 'rejected' => 'red'][$sub->status] ?? 'gray';
            @endphp
            <span class="badge badge-{{ $statusBadge }}">{{ ucfirst($sub->status) }}</span>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
