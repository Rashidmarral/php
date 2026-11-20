@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ t('admin.sections.page_title') }}</h1>
  <a href="/admin/sections/create" class="btn btn-primary btn-sm">+ {{ t('admin.sections.new_section') }}</a>
</div>
<p class="help-text" style="max-width:720px;margin-top:-8px;margin-bottom:20px;">
  {{ t('admin.sections.intro_before') }} <a href="/admin/content">{{ t('admin.content.title') }}</a> {{ t('admin.sections.intro_after') }}
</p>

@forelse ($sections as $slug => $group)
  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin-bottom:12px;">{{ $pages[$slug]['label'] ?? $slug }} <span class="badge badge-gray">{{ $slug }}</span></h3>
    <table class="data">
      <thead><tr><th>{{ t('common.type') }}</th><th>{{ t('admin.sections.heading_col') }}</th><th>{{ t('common.sort_order') }}</th><th>{{ t('common.status') }}</th><th></th></tr></thead>
      <tbody>
      @foreach ($group as $s)
        <tr>
          <td>{{ $types[$s->section_type] ?? $s->section_type }}</td>
          <td>{{ $s->title_en ?: '—' }}</td>
          <td>{{ $s->sort_order }}</td>
          <td><span class="badge badge-{{ $s->is_active ? 'green' : 'gray' }}">{{ $s->is_active ? t('admin.certificates.visible') : t('admin.certificates.hidden') }}</span></td>
          <td style="display:flex;gap:6px;">
            <a href="/admin/sections/{{ $s->id }}/edit" class="btn btn-sm btn-light">{{ t('common.edit') }}</a>
            <form method="post" action="/admin/sections/{{ $s->id }}/delete" onsubmit="return confirm('{{ t('admin.sections.remove_confirm') }}');">
              @csrf
              <button type="submit" class="btn btn-sm btn-danger">{{ t('common.remove') }}</button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@empty
  <div class="empty-state">
    <div class="icon">📝</div>
    <p>{{ t('admin.sections.none_yet') }} <a href="/admin/sections/create">{{ t('admin.sections.add_first_one') }}</a>.</p>
  </div>
@endforelse
@endsection
