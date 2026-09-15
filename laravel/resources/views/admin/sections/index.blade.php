@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>Custom Sections</h1>
  <a href="/admin/sections/create" class="btn btn-primary btn-sm">+ New section</a>
</div>
<p class="help-text" style="max-width:720px;margin-top:-8px;margin-bottom:20px;">
  Add whole new sections to any public page — a feature grid, a text block, a call-to-action banner,
  or a stat row — built from plain fields and rendered with the site's existing modern design. For
  editing the prebuilt sections' wording, use <a href="/admin/content">Page Content</a> instead.
</p>

@forelse ($sections as $slug => $group)
  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin-bottom:12px;">{{ $pages[$slug]['label'] ?? $slug }} <span class="badge badge-gray">{{ $slug }}</span></h3>
    <table class="data">
      <thead><tr><th>Type</th><th>Heading</th><th>Order</th><th>Status</th><th></th></tr></thead>
      <tbody>
      @foreach ($group as $s)
        <tr>
          <td>{{ $types[$s->section_type] ?? $s->section_type }}</td>
          <td>{{ $s->title_en ?: '—' }}</td>
          <td>{{ $s->sort_order }}</td>
          <td><span class="badge badge-{{ $s->is_active ? 'green' : 'gray' }}">{{ $s->is_active ? 'Visible' : 'Hidden' }}</span></td>
          <td style="display:flex;gap:6px;">
            <a href="/admin/sections/{{ $s->id }}/edit" class="btn btn-sm btn-light">Edit</a>
            <form method="post" action="/admin/sections/{{ $s->id }}/delete" onsubmit="return confirm('Remove this section?');">
              @csrf
              <button type="submit" class="btn btn-sm btn-danger">Remove</button>
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
    <p>No custom sections yet. <a href="/admin/sections/create">Add your first one</a>.</p>
  </div>
@endforelse
@endsection
