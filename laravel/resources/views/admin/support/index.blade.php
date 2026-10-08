@extends('layouts.admin')

@section('content')
<?php
$statusLabels = [
    'open' => t('admin.support.status_open'),
    'pending' => t('admin.support.status_pending'),
    'resolved' => t('admin.support.status_resolved'),
    'closed' => t('admin.support.status_closed'),
];
$priorityLabels = [
    'low' => t('admin.support.priority_low'),
    'normal' => t('admin.support.priority_normal'),
    'high' => t('admin.support.priority_high'),
    'urgent' => t('admin.support.priority_urgent'),
];
?>
<div class="page-head">
  <h1>{{ t('admin.support.title') }} <span class="badge badge-gray">{{ t('admin.support.open_count', ['count' => $openCount]) }}</span></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">{{ t('admin.support.hint') }}</p>

<div class="tabs" style="margin-bottom:20px;">
  <a href="/admin/support" class="{{ $activeStatus === '' ? 'active' : '' }}">{{ t('common.all') }}</a>
  @foreach ($statuses as $key => $label)
    <a href="/admin/support?status={{ $key }}" class="{{ $activeStatus === $key ? 'active' : '' }}">{{ $statusLabels[$key] ?? $label }}</a>
  @endforeach
</div>

<table class="data">
  <thead><tr><th>{{ t('admin.support.subject') }}</th><th>{{ t('common.company') }}</th><th>{{ t('admin.support.opened_by') }}</th><th>{{ t('admin.support.priority') }}</th><th>{{ t('common.status') }}</th><th>{{ t('admin.support.last_activity') }}</th><th></th></tr></thead>
  <tbody>
  @forelse ($tickets as $t)
    <tr>
      <td>{{ $t->subject }}<div class="help-text">{{ \App\Models\SupportTicket::CATEGORIES[$t->category] ?? $t->category }}</div></td>
      <td><a href="/admin/companies/{{ $t->company_id }}">{{ $t->company->name ?? '—' }}</a></td>
      <td>{{ $t->openedByUser->name ?? '—' }}</td>
      <td><span class="badge badge-{{ $t->priority === 'urgent' ? 'red' : ($t->priority === 'high' ? 'yellow' : 'gray') }}">{{ $priorityLabels[$t->priority] ?? $priorities[$t->priority] ?? $t->priority }}</span></td>
      <td><span class="badge badge-{{ $t->status === 'open' ? 'yellow' : ($t->status === 'pending' ? 'blue' : ($t->status === 'resolved' ? 'green' : 'gray')) }}">{{ $statusLabels[$t->status] ?? $statuses[$t->status] ?? $t->status }}</span></td>
      <td class="help-text">{{ $t->last_message_at?->diffForHumans() }}</td>
      <td><a href="/admin/support/{{ $t->id }}" class="btn btn-sm btn-light">{{ t('common.view') }}</a></td>
    </tr>
  @empty
    <tr><td colspan="7" class="help-text" style="text-align:center;padding:20px;">{{ t('admin.support.none_yet') }}</td></tr>
  @endforelse
  </tbody>
</table>
@include('admin.partials.pagination', ['paginator' => $tickets])
@endsection
