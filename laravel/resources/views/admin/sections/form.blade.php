@extends('layouts.admin')

@php
  $items = $section->items ?? [];
  while (count($items) < $maxItems) { $items[] = []; }
@endphp

@section('content')
<div class="page-head">
  <h1>{{ $section ? 'Edit section' : 'New section' }}</h1>
  <a href="/admin/sections" class="btn btn-light btn-sm">← Back</a>
</div>

<form method="post" action="{{ $section ? '/admin/sections/'.$section->id : '/admin/sections' }}" class="card" style="max-width:820px;">
  @csrf
  <div class="form-row">
    <div class="form-group">
      <label>Page</label>
      <input type="text" name="page_slug" list="page-options" value="{{ $section->page_slug ?? '' }}" placeholder="home" required>
      <datalist id="page-options">
        @foreach ($pages as $slug => $p)<option value="{{ $slug }}">{{ $p['label'] }}</option>@endforeach
      </datalist>
      <p class="help-text">One of home, features, about, pricing, contact, support, security — or a custom page's slug.</p>
    </div>
    <div class="form-group">
      <label>Section type</label>
      <select name="section_type" id="section-type">
        @foreach ($types as $key => $label)
          <option value="{{ $key }}" {{ ($section->section_type ?? 'feature_grid') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>Heading (English)</label><input type="text" name="title_en" value="{{ $section->title_en ?? '' }}"></div>
    <div class="form-group"><label>Heading (Arabic)</label><input type="text" name="title_ar" value="{{ $section->title_ar ?? '' }}" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label id="subtitle-label-en">Subtitle (English)</label>
      <input type="text" name="subtitle_en" value="{{ $section->subtitle_en ?? '' }}">
    </div>
    <div class="form-group">
      <label id="subtitle-label-ar">Subtitle (Arabic)</label>
      <input type="text" name="subtitle_ar" value="{{ $section->subtitle_ar ?? '' }}" dir="rtl">
    </div>
  </div>

  <div id="fields-text_block" class="section-type-fields">
    <div class="form-row">
      <div class="form-group"><label>Body text (English)</label><textarea name="body_en" rows="4">{{ $section->body_en ?? '' }}</textarea></div>
      <div class="form-group"><label>Body text (Arabic)</label><textarea name="body_ar" rows="4" dir="rtl">{{ $section->body_ar ?? '' }}</textarea></div>
    </div>
  </div>

  <div id="fields-cta_banner" class="section-type-fields">
    <div class="form-row">
      <div class="form-group"><label>Button text (English)</label><input type="text" name="button_text_en" value="{{ $section->button_text_en ?? '' }}"></div>
      <div class="form-group"><label>Button text (Arabic)</label><input type="text" name="button_text_ar" value="{{ $section->button_text_ar ?? '' }}" dir="rtl"></div>
    </div>
    <div class="form-group"><label>Button link</label><input type="text" name="button_url" value="{{ $section->button_url ?? '' }}" placeholder="/register or https://..."></div>
  </div>

  <div id="fields-feature_grid" class="section-type-fields">
    <h3 style="font-size:14px;margin-top:8px;">Items (leave a row blank to skip it)</h3>
    @foreach ($items as $i => $item)
      <div class="form-row" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px;">
        <div class="form-group" style="max-width:90px;"><label>Icon</label><input type="text" name="items[{{ $i }}][icon]" value="{{ $item['icon'] ?? '' }}" placeholder="🚀"></div>
        <div class="form-group"><label>Title (EN)</label><input type="text" name="items[{{ $i }}][title_en]" value="{{ $item['title_en'] ?? '' }}"></div>
        <div class="form-group"><label>Title (AR)</label><input type="text" name="items[{{ $i }}][title_ar]" value="{{ $item['title_ar'] ?? '' }}" dir="rtl"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Description (EN)</label><input type="text" name="items[{{ $i }}][desc_en]" value="{{ $item['desc_en'] ?? '' }}"></div>
        <div class="form-group"><label>Description (AR)</label><input type="text" name="items[{{ $i }}][desc_ar]" value="{{ $item['desc_ar'] ?? '' }}" dir="rtl"></div>
      </div>
    @endforeach
  </div>

  <div id="fields-stat_row" class="section-type-fields">
    <h3 style="font-size:14px;margin-top:8px;">Stats (leave a row blank to skip it)</h3>
    @foreach ($items as $i => $item)
      <div class="form-row" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px;">
        <div class="form-group" style="max-width:120px;"><label>Value</label><input type="text" name="items[{{ $i }}][value]" value="{{ $item['value'] ?? '' }}" placeholder="2,400+"></div>
        <div class="form-group"><label>Label (EN)</label><input type="text" name="items[{{ $i }}][label_en]" value="{{ $item['label_en'] ?? '' }}"></div>
        <div class="form-group"><label>Label (AR)</label><input type="text" name="items[{{ $i }}][label_ar]" value="{{ $item['label_ar'] ?? '' }}" dir="rtl"></div>
      </div>
    @endforeach
  </div>

  <div class="form-row" style="margin-top:18px;">
    <div class="form-group" style="max-width:140px;"><label>Sort order</label><input type="number" name="sort_order" value="{{ $section->sort_order ?? 0 }}"></div>
    <div class="form-group">
      <label>&nbsp;</label>
      <label style="font-weight:400;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" style="width:auto;" {{ ($section->is_active ?? true) ? 'checked' : '' }}> Visible on the page</label>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">{{ $section ? 'Save changes' : 'Create section' }}</button>
</form>

<script>
(function () {
  var select = document.getElementById('section-type');
  function update() {
    document.querySelectorAll('.section-type-fields').forEach(function (el) { el.style.display = 'none'; });
    var active = document.getElementById('fields-' + select.value);
    if (active) active.style.display = '';
    var isCta = select.value === 'cta_banner';
    document.getElementById('subtitle-label-en').textContent = isCta ? 'Body text (English)' : 'Subtitle (English)';
    document.getElementById('subtitle-label-ar').textContent = isCta ? 'Body text (Arabic)' : 'Subtitle (Arabic)';
  }
  select.addEventListener('change', update);
  update();
})();
</script>
@endsection
