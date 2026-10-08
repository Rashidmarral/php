@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/{{ $rfi->project_id }}">&larr; {{ $rfi->project ? local($rfi->project, 'name') : '' }}</a></p>
    <h1>{{ $rfi->displayNumber() }} &middot; {{ $rfi->subject }}</h1>
  </div>
  <a href="/app/projects/{{ $rfi->project_id }}/rfis" class="btn btn-light btn-sm">&larr; {{ t('common.back') }}</a>
</div>

<div class="grid grid-2" style="align-items:start;grid-template-columns:2fr 1fr;">
  <div class="card">
    <div class="ticket-thread">
      @foreach ($rfi->messages as $m)
        <div class="ticket-msg ticket-msg-{{ $m->sender_id === auth()->id() ? 'me' : 'them' }}">
          <div class="ticket-msg-meta"><strong>{{ $m->sender_name }}</strong> · {{ $m->created_at->diffForHumans() }}</div>
          <div class="ticket-msg-body">{{ $m->message }}</div>
          @if($m->attachment_path)
            <a href="{{ $m->attachment_path }}" target="_blank" rel="noopener" class="ticket-msg-attachment">📎 {{ $m->attachment_name }}</a>
          @endif
        </div>
      @endforeach
    </div>

    <form method="post" action="/app/rfis/{{ $rfi->id }}/reply" enctype="multipart/form-data" style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px;">
      @csrf
      <div class="form-group"><label>{{ t('user.rfi.reply') }}</label><textarea name="message" rows="4" required></textarea></div>
      <div class="form-group"><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
      <button type="submit" class="btn btn-primary">{{ t('user.rfi.send_reply') }}</button>
    </form>
  </div>

  <div class="card">
    <h3>{{ t('user.rfi.details') }}</h3>
    <p class="help-text">{{ t('user.rfi.raised_by') }}: {{ $rfi->raisedByUser->name ?? '—' }}</p>
    <p class="help-text">{{ t('user.rfi.assigned_to') }}: {{ $rfi->assignee->name ?? t('user.punch_list.unassigned') }}</p>
    <p class="help-text">{{ t('user.rfi.due_date') }}: {{ $rfi->due_date ? $rfi->due_date->format('Y-m-d') : '—' }}</p>
    @if($rfi->document)
      <p class="help-text">{{ t('user.rfi.reference_document') }}: <a href="{{ $rfi->document->file_path }}" target="_blank">{{ local($rfi->document, 'name') }}</a></p>
    @endif

    <form method="post" action="/app/rfis/{{ $rfi->id }}/status">
      @csrf
      <div class="form-group">
        <label>{{ t('common.status') }}</label>
        <select name="status">
          @foreach ($statuses as $key => $label)
            <option value="{{ $key }}" {{ $rfi->status === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <button type="submit" class="btn btn-outline btn-sm">{{ t('common.save') }}</button>
    </form>
  </div>
</div>
@endsection
