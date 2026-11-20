@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>Welcome, {{ $client->name }}</h1>
</div>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3>Projects</h3>
    @if ($projects->isEmpty())
      <p class="help-text">No projects yet.</p>
    @else
      <table class="data">
        <thead><tr><th>Name</th><th>Status</th></tr></thead>
        <tbody>
        @foreach ($projects as $p)
          <tr><td><a href="{{ url('/portal/projects/' . $p->id) }}">{{ $p->name }}</a></td><td><span class="badge badge-blue">{{ str_replace('_',' ',$p->status) }}</span></td></tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>

  <div class="card">
    <h3>Estimates</h3>
    @if ($estimates->isEmpty())
      <p class="help-text">No estimates yet.</p>
    @else
      <table class="data">
        <thead><tr><th>Title</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
        @foreach ($estimates as $e)
          <tr><td><a href="{{ url('/portal/estimates/' . $e->id) }}">{{ $e->title }}</a></td><td><span class="badge badge-gray">{{ $e->status }}</span></td><td>{!! money((float)$e->total) !!}</td></tr>
        @endforeach
        </tbody>
      </table>
    @endif
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3>Invoices</h3>
  @if ($invoices->isEmpty())
    <p class="help-text">No invoices yet.</p>
  @else
    <table class="data">
      <thead><tr><th>#</th><th>Status</th><th>Total</th><th>Due</th></tr></thead>
      <tbody>
      @foreach ($invoices as $i)
        <tr>
          <td><a href="{{ url('/portal/invoices/' . $i->id) }}">{{ $i->invoice_number }}</a></td>
          <td><span class="badge badge-{{ $i->status === 'paid' ? 'green' : 'yellow' }}">{{ $i->status }}</span></td>
          <td>{!! money((float)$i->total) !!}</td>
          <td class="help-text">{{ $i->due_date }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
