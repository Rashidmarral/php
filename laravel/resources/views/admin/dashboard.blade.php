@extends('layouts.admin')

@section('title', t('aside.dashboard'))

@section('content')
<div class="page-head">
  <h1>{{ t('aside.dashboard') }}</h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">{{ t('admin.dashboard.total_companies') }}</div><div class="value">{{ $totalCompanies }}</div></div>
  <div class="kpi"><div class="label">{{ t('admin.status.active') }}</div><div class="value">{{ $activeCompanies }}</div></div>
</div>
@endsection
