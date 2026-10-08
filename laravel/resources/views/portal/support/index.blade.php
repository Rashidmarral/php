@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>Support</h1>
  <a href="/portal/support/new" class="btn btn-primary btn-sm">New request</a>
</div>

<table class="data">
  <thead><tr><th>Subject</th><th>Status</th><th>Last activity</th><th></th></tr></thead>
  <tbody>
  @forelse ($tickets as $t)
    <tr>
      <td>{{ $t->subject }}</td>
      <td><span class="badge badge-{{ $t->status === 'open' ? 'yellow' : ($t->status === 'pending' ? 'blue' : ($t->status === 'resolved' ? 'green' : 'gray')) }}">{{ $statuses[$t->status] ?? $t->status }}</span></td>
      <td class="help-text">{{ $t->last_message_at?->diffForHumans() }}</td>
      <td><a href="/portal/support/{{ $t->id }}" class="btn btn-sm btn-light">View</a></td>
    </tr>
  @empty
    <tr><td colspan="4" class="help-text" style="text-align:center;padding:20px;">You haven't raised any support requests yet.</td></tr>
  @endforelse
  </tbody>
</table>
@endsection
