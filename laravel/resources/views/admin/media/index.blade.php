@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1>Media Library</h1>
</div>
<p class="help-text" style="max-width:760px;margin-top:-8px;margin-bottom:20px;">
  Upload images or add video links here, then paste the URL into a page's hero image/video fields
  (<a href="/admin/settings/header">Settings → Website Content</a>) or into any
  <a href="/admin/pages">custom page</a>'s content.
</p>

<div class="grid grid-2" style="align-items:start;grid-template-columns:1fr 2fr;gap:24px;">
  <div class="card">
    <h3>Add media</h3>
    <form method="post" action="/admin/media" enctype="multipart/form-data" id="media-form">
      @csrf
      <div class="form-group"><label>Title</label><input type="text" name="title" required placeholder="Homepage hero background"></div>
      <div class="form-group">
        <label>Type</label>
        <select name="type" id="media-type">
          <option value="image">Image (upload)</option>
          <option value="video">Video (link)</option>
        </select>
      </div>
      <div class="form-group" id="media-image-field">
        <label>Image file</label>
        <input type="file" name="image" accept="image/png,image/jpeg,image/webp,image/gif">
        <p class="help-text">PNG, JPG, WEBP, or GIF, up to 8MB.</p>
      </div>
      <div class="form-group" id="media-video-field" style="display:none;">
        <label>Video URL</label>
        <input type="url" name="video_url" placeholder="https://www.youtube.com/embed/... or a direct .mp4 link">
        <p class="help-text">Use a YouTube/Vimeo <em>embed</em> URL, or a direct .mp4 link.</p>
      </div>
      <button type="submit" class="btn btn-primary">Add to library</button>
    </form>
  </div>

  <div class="card">
    <h3>Library ({{ $media->count() }})</h3>
    @if($media->isEmpty())
      <p class="help-text">Nothing uploaded yet.</p>
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
            <input type="text" readonly value="{{ $m->url() }}" onclick="this.select();document.execCommand('copy');" style="font-size:11px;padding:6px 8px;margin-bottom:8px;" title="Click to copy">
            <form method="post" action="/admin/media/{{ $m->id }}/delete" onsubmit="return confirm('Remove this media item? Pages referencing its URL will show a broken link.');">
              @csrf
              <button type="submit" class="btn btn-sm btn-danger btn-block">Remove</button>
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
