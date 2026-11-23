@extends('layouts.supplier-portal')

@section('content')
<div class="page-head">
  <h1>{{ t('supplier_portal.submit_bill_title') }}</h1>
  <a href="{{ url('/supplier-portal') }}" class="btn btn-light">&larr; {{ t('supplier_portal.back') }}</a>
</div>

<div class="card" style="max-width:640px;">
  <p class="help-text" style="margin-top:0;">{{ t('supplier_portal.submit_bill_hint') }}</p>
  <form method="post" action="{{ url('/supplier-portal/bills') }}" enctype="multipart/form-data">
    @csrf
    <div class="form-row">
      <div class="form-group">
        <label>{{ t('supplier_portal.purchase_order_label') }}</label>
        <select name="purchase_order_id">
          <option value="">{{ t('common.none') }}</option>
          @foreach ($purchaseOrders as $po)
            <option value="{{ $po->id }}">{{ $po->po_number }} ({!! money((float) $po->total) !!})</option>
          @endforeach
        </select>
      </div>
      <div class="form-group">
        <label>{{ t('supplier_portal.subcontract_label') }}</label>
        <select name="subcontract_id">
          <option value="">{{ t('common.none') }}</option>
          @foreach ($subcontracts as $sc)
            <option value="{{ $sc->id }}">{{ $sc->title }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <p class="help-text" style="margin-top:-8px;">{{ t('supplier_portal.pick_one_hint') }}</p>

    <div class="form-group"><label>{{ t('common.description') }}</label><input type="text" name="description" required maxlength="255"></div>

    <div class="form-row">
      <div class="form-group"><label>{{ t('common.amount') }}</label><input type="number" name="amount" step="0.01" min="0.01" required></div>
      <div class="form-group">
        <label>{{ t('common.category') }}</label>
        <select name="category">
          @foreach ($categories as $key => $label)
            <option value="{{ $key }}">{{ $label }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group"><label>{{ t('user.projects.bill_date') }}</label><input type="date" name="bill_date"></div>
      <div class="form-group"><label>{{ t('user.projects.reference_number') }}</label><input type="text" name="reference" maxlength="100"></div>
    </div>

    <div class="form-group"><label>{{ t('supplier_portal.receipt_label') }}</label><input type="file" name="receipt" accept="application/pdf,image/jpeg,image/png"></div>

    <button type="submit" class="btn btn-primary">{{ t('supplier_portal.submit_bill_btn') }}</button>
  </form>
</div>
@endsection
