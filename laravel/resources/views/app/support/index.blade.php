@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.support.title') }}</h1>
  @if($tab === 'mine')
    <a href="/app/support/new" class="btn btn-primary btn-sm">{{ t('user.support.new_ticket') }}</a>
  @endif
</div>

@if($hasPrioritySupport)
  <p class="help-text" style="margin-top:-12px;margin-bottom:16px;">⭐ {{ t('user.support.priority_badge') }}</p>
@endif

<div class="tabs" style="margin-bottom:20px;">
  <a href="/app/support?tab=mine" class="{{ $tab === 'mine' ? 'active' : '' }}">{{ t('user.support.tab_mine') }}</a>
  <a href="/app/support?tab=clients" class="{{ $tab === 'clients' ? 'active' : '' }}">{{ t('user.support.tab_clients') }}</a>
</div>

<table class="data">
  <thead><tr><th>{{ t('user.support.subject') }}</th>@if($tab === 'clients')<th>{{ t('side.clients') }}</th>@endif<th>{{ t('common.status') }}</th><th>{{ t('user.support.last_activity') }}</th><th></th></tr></thead>
  <tbody>
  @forelse ($tickets as $t)
    <tr>
      <td>{{ $t->subject }}<div class="help-text">{{ \App\Models\SupportTicket::CATEGORIES[$t->category] ?? $t->category }}</div></td>
      @if($tab === 'clients')<td>{{ $t->client->name ?? '—' }}</td>@endif
      <td><span class="badge badge-{{ $t->status === 'open' ? 'yellow' : ($t->status === 'pending' ? 'blue' : ($t->status === 'resolved' ? 'green' : 'gray')) }}">{{ $statuses[$t->status] ?? $t->status }}</span></td>
      <td class="help-text">{{ $t->last_message_at?->diffForHumans() }}</td>
      <td><a href="/app/support/{{ $t->id }}" class="btn btn-sm btn-light">{{ t('common.view') }}</a></td>
    </tr>
  @empty
    <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;">{{ $tab === 'mine' ? t('user.support.none_mine') : t('user.support.none_clients') }}</td></tr>
  @endforelse
  </tbody>
</table>
@endsection
