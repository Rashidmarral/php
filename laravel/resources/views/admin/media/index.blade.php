@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>{{ t('admin.media.title') }}</h1>
</div>
<p class="help-text" style="max-width:760px;margin-top:-8px;margin-bottom:20px;">
  {{ t('admin.media.intro_before') }}
  (<a href="/admin/settings/header">{{ t('admin.media.settings_website_content') }}</a>) {{ t('admin.media.intro_mid') }}
  <a href="/admin/pages">{{ t('admin.media.custom_page_link') }}</a>{{ t('admin.media.intro_after') }}
</p>

<div class="grid grid-2" style="align-items:start;grid-template-columns:1fr 2fr;gap:24px;">
  <div class="card">
    <h3>{{ t('admin.media.add_media') }}</h3>
    <form method="post" action="/admin/media" enctype="multipart/form-data" id="media-form">
      @csrf
      <div class="form-group"><label>{{ t('common.title') }}</label><input type="text" name="title" required placeholder="Homepage hero background"></div>
      <div class="form-group">
        <label>{{ t('common.type') }}</label>
        <select name="type" id="media-type">
          <option value="image">{{ t('admin.media.type_image') }}</option>
          <option value="video">{{ t('admin.media.type_video') }}</option>
        </select>
      </div>
      <div class="form-group" id="media-image-field">
        <label>{{ t('admin.media.image_file') }}</label>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
        <p class="help-text">{{ t('admin.media.image_file_hint') }}</p>
      </div>
      <div class="form-group" id="media-video-field" style="display:none;">
        <label>{{ t('admin.media.video_url') }}</label>
        <input type="url" name="video_url" placeholder="https://www.youtube.com/embed/... or a direct .mp4 link">
        <p class="help-text">{{ t('admin.media.video_url_hint') }}</p>
      </div>
      <button type="submit" class="btn btn-primary">{{ t('admin.media.add_to_library') }}</button>
    </form>
  </div>

  <div class="card">
    <h3>{{ t('admin.media.library_count', ['count' => $media->count()]) }}</h3>
    @if($media->isEmpty())
      <p class="help-text">{{ t('admin.media.nothing_uploaded') }}</p>
    @else
      <div class="grid grid-3" style="gap:14px;">
        @foreach ($media as $m)
          <div class="card" style="padding:12px;">
            @if($m->type === 'image')
              <img src="{{ $m->file_path }}" alt="{{ $m->title }}" style="width:100%;height:110px;object-fit:cover;border-radius:8px;margin-bottom:8px;">
            @else
              <div style="width:100%;height:110px;border-radius:8px;margin-bottom:8px;background:var(--brand-light);display:flex;align-items:center;justify-content:center;font-size:32px;">🎬</div>
            @endif
            <div style="font-weight:600;font-size:13px;margin-bottom:4px;">{{ $m->title }}</div>
            <input type="text" readonly value="{{ $m->url() }}" onclick="this.select();document.execCommand('copy');" style="font-size:11px;padding:6px 8px;margin-bottom:8px;" title="{{ t('admin.media.click_to_copy') }}">
            <form method="post" action="/admin/media/{{ $m->id }}/delete" onsubmit="return confirm('{{ t('admin.media.remove_confirm') }}');">
              @csrf
              <button type="submit" class="btn btn-sm btn-danger btn-block">{{ t('common.remove') }}</button>
            </form>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>

<script>
document.getElementById('media-type').addEventListener('change', function () {
  const isVideo = this.value === 'video';
  document.getElementById('media-image-field').style.display = isVideo ? 'none' : '';
  document.getElementById('media-video-field').style.display = isVideo ? '' : 'none';
});
</script>
@endsection
