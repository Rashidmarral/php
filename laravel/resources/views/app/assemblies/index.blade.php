@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.assemblies.title') }}</h1>
  <a href="/app/assemblies/create" class="btn btn-primary">{{ t('user.assemblies.new') }}</a>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">{{ t('user.assemblies.index_hint') }}</p>

@if (empty($assemblies))
  <div class="card empty-state">
    <div class="icon">🧱</div>
    <h3>{{ t('user.assemblies.no_assemblies_title') }}</h3>
    <p>{{ t('user.assemblies.no_assemblies_hint') }}</p>
    <a href="/app/assemblies/create" class="btn btn-primary">{{ t('user.assemblies.new') }}</a>
  </div>
@else
  <table class="data">
    <thead><tr>
      <th>{{ t('common.name') }}</th>
      <th>{{ t('user.assemblies.base_uom') }}</th>
      <th>{{ t('user.assemblies.item_count') }}</th>
      <th>{{ t('common.status') }}</th>
      <th></th>
    </tr></thead>
    <tbody>
    @foreach ($assemblies as $a)
      <tr>
        <td>
          {{ local($a, 'name') }}
          @if (!empty($a['description']))<br><span class="help-text">{{ $a['description'] }}</span>@endif
        </td>
        <td>{{ $a['base_uom'] }}</td>
        <td>{{ $a['items_count'] }}</td>
        <td>
          @if ($a['is_active'])
            <span class="badge badge-green">{{ t('common.active') }}</span>
          @else
            <span class="badge badge-gray">{{ t('common.inactive') }}</span>
          @endif
        </td>
        <td style="display:flex;gap:8px;">
          <a href="/app/assemblies/{{ $a['id'] }}/edit" class="btn btn-sm btn-light">{{ t('common.edit') }}</a>
          <form method="post" action="/app/assemblies/{{ $a['id'] }}/delete" onsubmit="return confirm('{{ t('user.assemblies.remove_confirm') }}');">
            @csrf
            <button type="submit" class="btn btn-sm btn-danger">{{ t('common.delete') }}</button>
          </form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
@endif

@endsection
