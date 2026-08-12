@extends('layouts.portal')

@section('content')
<div class="page-head">
  <h1>{{ $estimate->title }}</h1>
  <a href="{{ url('/portal') }}" class="btn btn-light">← Back</a>
</div>

<div class="card" style="max-width:820px;">
  <span class="badge badge-{{ ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$estimate->status] ?? 'gray' }}" style="margin-bottom:14px;display:inline-block;">{{ $estimate->status }}</span>
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit cost</th><th>Total</th></tr></thead>
    <tbody>
      @foreach ($items as $it)
        <tr><td>{{ $it->description }}</td><td>{{ $it->qty }}</td><td>{!! money((float)$it->unit_cost) !!}</td><td>{!! money((float)$it->total) !!}</td></tr>
      @endforeach
    </tbody>
  </table>
  <div class="total-row" style="margin-top:14px;">Total: {!! money((float)$estimate->total) !!}</div>
</div>
@endsection
