@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>Support Tickets <span class="badge badge-gray">{{ $openCount }} open</span></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">Support requests submitted by company owners/staff from their panel. Client-to-company tickets are handled by the company itself, not shown here.</p>

<div class="tabs" style="margin-bottom:20px;">
  <a href="/admin/support" class="{{ $activeStatus === '' ? 'active' : '' }}">All</a>
  @foreach ($statuses as $key => $label)
    <a href="/admin/support?status={{ $key }}" class="{{ $activeStatus === $key ? 'active' : '' }}">{{ $label }}</a>
  @endforeach
</div>

<table class="data">
  <thead><tr><th>Subject</th><th>Company</th><th>Opened by</th><th>Priority</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
  <tbody>
  @forelse ($tickets as $t)
    <tr>
      <td>{{ $t->subject }}<div class="help-text">{{ \App\Models\SupportTicket::CATEGORIES[$t->category] ?? $t->category }}</div></td>
      <td><a href="/admin/companies/{{ $t->company_id }}">{{ $t->company->name ?? '—' }}</a></td>
      <td>{{ $t->openedByUser->name ?? '—' }}</td>
      <td><span class="badge badge-{{ $t->priority === 'urgent' ? 'red' : ($t->priority === 'high' ? 'yellow' : 'gray') }}">{{ $priorities[$t->priority] ?? $t->priority }}</span></td>
      <td><span class="badge badge-{{ $t->status === 'open' ? 'yellow' : ($t->status === 'pending' ? 'blue' : ($t->status === 'resolved' ? 'green' : 'gray')) }}">{{ $statuses[$t->status] ?? $t->status }}</span></td>
      <td class="help-text">{{ $t->last_message_at?->diffForHumans() }}</td>
      <td><a href="/admin/support/{{ $t->id }}" class="btn btn-sm btn-light">Open</a></td>
    </tr>
  @empty
    <tr><td colspan="7" class="help-text" style="text-align:center;padding:20px;">No support tickets yet.</td></tr>
  @endforelse
  </tbody>
</table>
@include('admin.partials.pagination', ['paginator' => $tickets])
@endsection
