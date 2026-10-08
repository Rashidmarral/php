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
