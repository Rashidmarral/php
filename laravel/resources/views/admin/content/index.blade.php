@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ t('admin.content.title') }}</h1>
</div>
<p class="help-text" style="max-width:720px;margin-top:-8px;margin-bottom:20px;">
  {{ t('admin.content.intro_before') }} <a href="/admin/media">{{ t('admin.media.title') }}</a> {{ t('admin.content.intro_mid') }}
  <a href="/admin/settings/header">{{ t('admin.content.website_content_link') }}</a>. {{ t('admin.content.intro_after') }}
  <a href="/admin/translations">{{ t('admin.settings.translations_title') }}</a>.
</p>

<div class="grid grid-3">
  @foreach ($pages as $slug => $page)
    <a href="/admin/content/{{ $slug }}" class="card feature-card reveal" style="display:block;">
      <h3 style="margin-bottom:4px;">{{ $page['label'] }}</h3>
      <p class="help-text" style="margin-bottom:10px;">{{ t('admin.content.editable_fields_count', ['count' => $counts[$slug]]) }}</p>
      <span class="badge badge-gray">{{ $page['url'] }}</span>
    </a>
  @endforeach
</div>
@endsection
