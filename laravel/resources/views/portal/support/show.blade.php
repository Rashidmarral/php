@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>{{ $ticket->subject }}</h1>
  <a href="/portal/support" class="btn btn-light btn-sm">← Back</a>
</div>

<div class="card" style="max-width:720px;">
  <div class="ticket-thread">
    @foreach ($ticket->messages as $m)
      <div class="ticket-msg ticket-msg-{{ $m->sender_type === 'client' ? 'me' : 'them' }}">
        <div class="ticket-msg-meta"><strong>{{ $m->sender_name }}</strong> · {{ $m->created_at->diffForHumans() }}</div>
        <div class="ticket-msg-body">{{ $m->message }}</div>
        @if($m->attachment_path)
          <a href="{{ $m->attachment_path }}" target="_blank" rel="noopener" class="ticket-msg-attachment">📎 {{ $m->attachment_name }}</a>
        @endif
      </div>
    @endforeach
  </div>

  <form method="post" action="/portal/support/{{ $ticket->id }}/reply" enctype="multipart/form-data" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">
    @csrf
    <div class="form-group"><label>Reply</label><textarea name="message" rows="4" required></textarea></div>
    <div class="form-group"><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
    <button type="submit" class="btn btn-primary">Send</button>
  </form>
</div>
@endsection
