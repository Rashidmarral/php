@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ $ticket->subject }}</h1>
  <a href="/admin/support" class="btn btn-light btn-sm">← Back to tickets</a>
</div>

<div class="grid grid-2" style="align-items:start;grid-template-columns:2fr 1fr;">
  <div class="card">
    <div class="ticket-thread">
      @foreach ($ticket->messages as $m)
        <div class="ticket-msg ticket-msg-{{ $m->sender_type === 'admin' ? 'me' : 'them' }}">
          <div class="ticket-msg-meta"><strong>{{ $m->sender_name }}</strong> · {{ $m->created_at->diffForHumans() }}</div>
          <div class="ticket-msg-body">{{ $m->message }}</div>
          @if($m->attachment_path)
            <a href="{{ $m->attachment_path }}" target="_blank" rel="noopener" class="ticket-msg-attachment">📎 {{ $m->attachment_name }}</a>
          @endif
        </div>
      @endforeach
    </div>

    @if(auth()->user()->isSuperAdmin())
      <form method="post" action="/admin/support/{{ $ticket->id }}/reply" enctype="multipart/form-data" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">
        @csrf
        <div class="form-group"><label>Reply</label><textarea name="message" rows="4" required placeholder="Type your reply…"></textarea></div>
        <div class="form-group"><label>Attach a file (optional)</label><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
        <button type="submit" class="btn btn-primary">Send reply</button>
      </form>
    @endif
  </div>

  <div class="card">
    <h3>Ticket details</h3>
    <p class="help-text">Company: <a href="/admin/companies/{{ $ticket->company_id }}">{{ $ticket->company->name ?? '—' }}</a></p>
    <p class="help-text">Opened by: {{ $ticket->openedByUser->name ?? '—' }}</p>
    <p class="help-text">Category: {{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</p>

    @if(auth()->user()->isSuperAdmin())
      <form method="post" action="/admin/support/{{ $ticket->id }}/status">
        @csrf
        <div class="form-group">
          <label>Status</label>
          <select name="status">
            @foreach ($statuses as $key => $label)
              <option value="{{ $key }}" {{ $ticket->status === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label>Priority</label>
          <select name="priority">
            @foreach ($priorities as $key => $label)
              <option value="{{ $key }}" {{ $ticket->priority === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <button type="submit" class="btn btn-outline btn-sm">Update</button>
      </form>
    @else
      <p><span class="badge badge-gray">{{ $statuses[$ticket->status] ?? $ticket->status }}</span></p>
    @endif
  </div>
</div>
@endsection
