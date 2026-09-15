@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ $ticket->subject }}</h1>
  <a href="/app/support?tab={{ $ticket->channel === 'company' ? 'clients' : 'mine' }}" class="btn btn-light btn-sm">← {{ t('common.back') }}</a>
</div>

<div class="grid grid-2" style="align-items:start;grid-template-columns:2fr 1fr;">
  <div class="card">
    <div class="ticket-thread">
      @foreach ($ticket->messages as $m)
        <div class="ticket-msg ticket-msg-{{ $m->sender_type === 'company' ? 'me' : 'them' }}">
          <div class="ticket-msg-meta"><strong>{{ $m->sender_name }}</strong> · {{ $m->created_at->diffForHumans() }}</div>
          <div class="ticket-msg-body">{{ $m->message }}</div>
          @if($m->attachment_path)
            <a href="{{ $m->attachment_path }}" target="_blank" rel="noopener" class="ticket-msg-attachment">📎 {{ $m->attachment_name }}</a>
          @endif
        </div>
      @endforeach
    </div>

    <form method="post" action="/app/support/{{ $ticket->id }}/reply" enctype="multipart/form-data" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">
      @csrf
      <div class="form-group"><label>{{ t('user.support.reply') }}</label><textarea name="message" rows="4" required></textarea></div>
      <div class="form-group"><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
      <button type="submit" class="btn btn-primary">{{ t('user.support.send_reply') }}</button>
    </form>
  </div>

  <div class="card">
    <h3>{{ t('user.support.ticket_details') }}</h3>
    <p class="help-text">{{ \App\Models\SupportTicket::CHANNELS[$ticket->channel] ?? $ticket->channel }}</p>
    @if($ticket->channel === 'company')
      <p class="help-text">{{ t('side.clients') }}: {{ $ticket->client->name ?? '—' }}</p>
    @endif
    <p class="help-text">{{ t('user.support.category') }}: {{ \App\Models\SupportTicket::CATEGORIES[$ticket->category] ?? $ticket->category }}</p>

    <form method="post" action="/app/support/{{ $ticket->id }}/status">
      @csrf
      <div class="form-group">
        <label>{{ t('common.status') }}</label>
        <select name="status">
          @foreach ($statuses as $key => $label)
            <option value="{{ $key }}" {{ $ticket->status === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-outline btn-sm">{{ t('common.save') }}</button>
    </form>
  </div>
</div>
@endsection
