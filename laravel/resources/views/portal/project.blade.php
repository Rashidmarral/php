@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>{{ $project->name }}</h1>
  <a href="{{ url('/portal') }}" class="btn btn-light">← Back</a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-blue">{{ str_replace('_',' ',$project->status) }}</span></div></div>
  <div class="kpi"><div class="label">Start</div><div class="value" style="font-size:16px;">{{ $project->start_date ?: '—' }}</div></div>
  <div class="kpi"><div class="label">End</div><div class="value" style="font-size:16px;">{{ $project->end_date ?: '—' }}</div></div>
</div>

@if($project->description)
  <div class="card" style="margin-bottom:20px;"><p>{{ $project->description }}</p></div>
@endif

<div class="card" style="margin-bottom:20px;">
  <h3>{{ t('portal.selections.title') }}</h3>
  <p class="help-text" style="margin-top:-6px;">{{ t('portal.selections.card_hint') }}</p>
  <a href="{{ url('/portal/projects/' . $project->id . '/selections') }}" class="btn btn-outline">{{ t('portal.selections.view_selections') }}</a>
</div>

@if ($updates->isNotEmpty())
<div class="card" style="margin-bottom:20px;">
  <h3>{{ t('portal.project_updates.title') }}</h3>
  @foreach ($updates as $update)
    <div style="padding:12px 0;border-top:1px solid var(--border);">
      @if ($update->title)
        <strong>{{ $update->title }}</strong>
      @endif
      <p style="white-space:pre-line;margin:6px 0 4px;">{{ $update->body }}</p>
      <p class="help-text" style="margin:0;">{{ t('portal.project_updates.posted_on') }} {{ $update->published_at->format('Y-m-d') }}</p>
    </div>
  @endforeach
</div>
@endif

<div class="card">
  <h3>Schedule</h3>
  @if ($tasks->isEmpty())
    <p class="help-text">No schedule published yet.</p>
  @else
    <table class="data">
      <thead><tr><th>Task</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
      <tbody>
      @foreach ($tasks as $t)
        <tr><td>{{ $t->title }}</td><td>{{ $t->start_date }}</td><td>{{ $t->end_date }}</td><td><span class="badge badge-gray">{{ str_replace('_',' ',$t->status) }}</span></td></tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
