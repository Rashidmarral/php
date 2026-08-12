@extends('layouts.app')

@section('title', t('user.dashboard.title'))

@section('content')
<div class="page-head">
  <h1>{{ t('user.dashboard.title') }}</h1>
</div>

<div class="card">
  <p>{{ auth()->user()->name }} — {{ auth()->user()->company?->name }}</p>
  <p class="help-text">Laravel conversion foundation is live: MySQL schema, Eloquent models, auth/RBAC, and bilingual i18n are wired up. Full module ports (projects, estimates, invoices, etc.) land in the next phase.</p>
</div>
@endsection
