@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.estimates.new_title') }}</h1>
  <a href="{{ url('/app/estimates') }}" class="btn btn-light">{{ t('user.estimates.back_to_estimates') }}</a>
</div>

<form method="post" action="{{ url('/app/estimates') }}" class="card" style="max-width:1080px;">
  @csrf
  <div class="form-row">
    <div class="form-group"><label>{{ t('user.estimates.title_en') }}</label><input type="text" name="title" required maxlength="150" placeholder="e.g. Villa Renovation Estimate"></div>
    <div class="form-group"><label>{{ t('user.estimates.title_ar') }}</label><input type="text" name="title_ar" dir="rtl" maxlength="150" placeholder="عنوان التسعيرة بالعربية"></div>
    <div class="form-group"><label>{{ t('user.estimates.valid_until') }}</label><input type="date" name="valid_until"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>{{ t('common.client') }}</label>
      <select name="client_id" class="combo-select" data-placeholder="{{ t('common.search_clients_placeholder') }}" data-no-results="{{ t('common.no_clients_found') }}">
        <option value="">{{ t('user.projects.no_client') }}</option>
        @foreach ($clients as $c)<option value="{{ $c['id'] }}" data-ar="{{ $c['name_ar'] ?? '' }}">{{ $c['name'] }}</option>@endforeach
      </select>
    </div>
    <div class="form-group">
      <label>{{ t('user.estimates.link_project_optional') }}</label>
      <select name="project_id" class="combo-select" data-placeholder="{{ t('common.search_projects_placeholder') }}" data-no-results="{{ t('common.no_projects_found') }}">
        <option value="">{{ t('user.invoices.no_project') }}</option>
        @foreach ($projects as $p)<option value="{{ $p['id'] }}" data-ar="{{ $p['name_ar'] ?? '' }}">{{ $p['name'] }}</option>@endforeach
      </select>
    </div>
  </div>

  @include('app.estimates._line-items', ['prefillItems' => [], 'markupPercent' => $defaultMarkupPercent, 'taxRateId' => $defaultTaxRateId])

  <button type="submit" class="btn btn-primary" style="margin-top:16px;">{{ t('user.estimates.create_estimate') }}</button>
</form>

@endsection
