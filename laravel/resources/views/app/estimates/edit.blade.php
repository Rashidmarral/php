@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('common.edit') }} — {{ local($estimate, 'title') }}</h1>
  <a href="{{ url('/app/estimates/' . $estimate['id']) }}" class="btn btn-light">{{ t('user.estimates.back_to_estimates') }}</a>
</div>

<form method="post" action="{{ url('/app/estimates/' . $estimate['id'] . '/update') }}" class="card" style="max-width:1080px;">
  @csrf
  <div class="form-row">
    <div class="form-group"><label>{{ t('user.estimates.title_en') }}</label><input type="text" name="title" required value="{{ $estimate['title'] }}" placeholder="e.g. Villa Renovation Estimate"></div>
    <div class="form-group"><label>{{ t('user.estimates.title_ar') }}</label><input type="text" name="title_ar" dir="rtl" value="{{ $estimate['title_ar'] }}" placeholder="عنوان التسعيرة بالعربية"></div>
    <div class="form-group"><label>{{ t('user.estimates.valid_until') }}</label><input type="date" name="valid_until" value="{{ $estimate['valid_until'] }}"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>{{ t('common.client') }}</label>
      <select name="client_id">
        <option value="">{{ t('user.projects.no_client') }}</option>
        @foreach ($clients as $c)<option value="{{ $c['id'] }}" {{ (string)$estimate['client_id'] === (string)$c['id'] ? 'selected' : '' }}>{{ $c['name'] }}</option>@endforeach
      </select>
    </div>
    <div class="form-group">
      <label>{{ t('user.estimates.link_project_optional') }}</label>
      <select name="project_id">
        <option value="">{{ t('user.invoices.no_project') }}</option>
        @foreach ($projects as $p)<option value="{{ $p['id'] }}" {{ (string)$estimate['project_id'] === (string)$p['id'] ? 'selected' : '' }}>{{ $p['name'] }}</option>@endforeach
      </select>
    </div>
  </div>

  @include('app.estimates._line-items', ['prefillItems' => $prefillItems, 'markupPercent' => $estimate['markup_percent'], 'taxRateId' => $estimate['tax_rate_id']])

  <button type="submit" class="btn btn-primary" style="margin-top:16px;">{{ t('common.save_changes') }}</button>
</form>

@endsection
