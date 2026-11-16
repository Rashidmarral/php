@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/documents">&larr; {{ t('user.documents.title') }}</a></p>
    <h1>{{ local($document, 'name') }}</h1>
  </div>
</div>

@if($placing)
  <div class="alert" style="background:#eef3fb;color:#2c5282;border:1px solid #bcd6f2;margin-bottom:16px;">
    {{ t('user.documents.placing_pin_hint', ['label' => $placing['label']]) }}
  </div>
@endif

<div class="card" style="padding:12px;overflow:auto;">
  @if($isImage)
    <div id="pin-canvas-wrap" style="position:relative;display:inline-block;max-width:100%;">
      <canvas id="pin-canvas" style="max-width:100%;display:block;border:1px solid var(--border);{{ $placing ? 'cursor:crosshair;' : '' }}"></canvas>
      <img id="pin-image" src="{{ $document->file_path }}" style="display:none;">
      @foreach($pins as $pin)
        <a
          href="{{ $pin['link'] ?? '#' }}"
          title="{{ $pin['label'] }}"
          class="document-pin-marker"
          style="position:absolute;left:{{ $pin['x'] }}%;top:{{ $pin['y'] }}%;transform:translate(-50%,-50%);width:22px;height:22px;border-radius:50%;background:#d4a017;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;text-decoration:none;border:2px solid #fff;box-shadow:0 1px 3px rgba(0,0,0,.4);{{ $pin['link'] ? '' : 'pointer-events:none;' }}"
        >{{ $loop->iteration }}</a>
      @endforeach
    </div>
  @else
    <div class="empty-state">
      <div class="icon">📄</div>
      <p>{{ t('user.documents.cannot_preview_file_type') }}</p>
      <a href="{{ $document->file_path }}" target="_blank" rel="noopener">{{ t('user.documents.open_file') }}</a>
    </div>
  @endif
</div>

@if(empty($pins))
  <p class="help-text" style="margin-top:12px;">{{ t('user.documents.no_pins_yet') }}</p>
@else
  <div class="card" style="margin-top:16px;">
    <h3 style="font-size:14px;">{{ t('user.documents.pins_label') }}</h3>
    <div class="card-list">
      @foreach($pins as $pin)
        <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding:6px 0;">
          <div style="font-size:13px;">#{{ $loop->iteration }} &middot; {{ $pin['label'] }}</div>
          @if($pin['link'])
            <a href="{{ $pin['link'] }}" class="btn btn-sm btn-light">{{ t('common.view') }}</a>
          @endif
        </div>
      @endforeach
    </div>
  </div>
@endif

@if($placing)
<form method="post" action="/app/documents/{{ $document->id }}/pins" id="pin-form" style="display:none;">
  @csrf
  <input type="hidden" name="pinnable_type" value="{{ $placing['type'] }}">
  <input type="hidden" name="pinnable_id" value="{{ $placing['id'] }}">
  <input type="hidden" name="x" id="pin-x">
  <input type="hidden" name="y" id="pin-y">
</form>
@endif

<script>
(function() {
  const canvas = document.getElementById('pin-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const img = document.getElementById('pin-image');
  const placing = <?= $placing ? 'true' : 'false' ?>;

  function draw() {
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    ctx.drawImage(img, 0, 0);
  }
  if (img.complete) draw(); else img.onload = draw;

  if (!placing) return;

  // Same canvasPoint() mechanic as takeoffs/show.blade.php: converts a mouse click into
  // image-pixel coordinates accounting for the canvas's rendered-vs-natural size ratio.
  function canvasPoint(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
  }

  canvas.addEventListener('click', (e) => {
    const point = canvasPoint(e);
    document.getElementById('pin-x').value = ((point.x / canvas.width) * 100).toFixed(3);
    document.getElementById('pin-y').value = ((point.y / canvas.height) * 100).toFixed(3);
    document.getElementById('pin-form').submit();
  });
})();
</script>
@endsection
