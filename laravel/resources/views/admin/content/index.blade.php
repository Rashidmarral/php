@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>Page Content</h1>
</div>
<p class="help-text" style="max-width:720px;margin-top:-8px;margin-bottom:20px;">
  Edit the actual wording shown on each public marketing page — headlines, section text, FAQs — as
  plain text, in English and Arabic side by side. No HTML; the page keeps its modern design and just
  renders your words. For images/video see the <a href="/admin/media">Media Library</a> and
  <a href="/admin/settings/header">Settings → Website content</a>. For anything not listed here, the
  full list of on-page strings is in <a href="/admin/translations">Translations</a>.
</p>

<div class="grid grid-3">
  @foreach ($pages as $slug => $page)
    <a href="/admin/content/{{ $slug }}" class="card feature-card reveal" style="display:block;">
      <h3 style="margin-bottom:4px;">{{ $page['label'] }}</h3>
      <p class="help-text" style="margin-bottom:10px;">{{ $counts[$slug] }} editable text fields</p>
      <span class="badge badge-gray">{{ $page['url'] }}</span>
    </a>
  @endforeach
</div>
@endsection
