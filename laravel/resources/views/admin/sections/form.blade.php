@extends('layouts.admin')

@php
  $items = $section->items ?? [];
  while (count($items) < $maxItems) { $items[] = []; }
@endphp

@section('content')
<div class="page-head">
  <h1>{{ $section ? t('admin.sections.edit_title') : t('admin.sections.new_title') }}</h1>
  <a href="/admin/sections" class="btn btn-light btn-sm">← {{ t('common.back') }}</a>
</div>

<form method="post" action="{{ $section ? '/admin/sections/'.$section->id : '/admin/sections' }}" class="card" style="max-width:820px;">
  @csrf
  <div class="form-row">
    <div class="form-group">
      <label>{{ t('admin.sections.page') }}</label>
      <input type="text" name="page_slug" list="page-options" value="{{ $section->page_slug ?? '' }}" placeholder="home" required>
      <datalist id="page-options">
        @foreach ($pages as $slug => $p)<option value="{{ $slug }}">{{ $p['label'] }}</option>@endforeach
      </datalist>
      <p class="help-text">{{ t('admin.sections.page_hint') }}</p>
    </div>
    <div class="form-group">
      <label>{{ t('admin.sections.section_type') }}</label>
      <select name="section_type" id="section-type">
        @foreach ($types as $key => $label)
          <option value="{{ $key }}" {{ ($section->section_type ?? 'feature_grid') === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group"><label>{{ t('admin.sections.heading_en') }}</label><input type="text" name="title_en" value="{{ $section->title_en ?? '' }}"></div>
    <div class="form-group"><label>{{ t('admin.sections.heading_ar') }}</label><input type="text" name="title_ar" value="{{ $section->title_ar ?? '' }}" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label id="subtitle-label-en">{{ t('admin.sections.subtitle_en') }}</label>
      <input type="text" name="subtitle_en" value="{{ $section->subtitle_en ?? '' }}">
    </div>
    <div class="form-group">
      <label id="subtitle-label-ar">{{ t('admin.sections.subtitle_ar') }}</label>
      <input type="text" name="subtitle_ar" value="{{ $section->subtitle_ar ?? '' }}" dir="rtl">
    </div>
  </div>

  <div id="fields-text_block" class="section-type-fields">
    <div class="form-row">
      <div class="form-group"><label>{{ t('admin.sections.body_text_en') }}</label><textarea name="body_en" rows="4">{{ $section->body_en ?? '' }}</textarea></div>
      <div class="form-group"><label>{{ t('admin.sections.body_text_ar') }}</label><textarea name="body_ar" rows="4" dir="rtl">{{ $section->body_ar ?? '' }}</textarea></div>
    </div>
  </div>

  <div id="fields-cta_banner" class="section-type-fields">
    <div class="form-row">
      <div class="form-group"><label>{{ t('admin.sections.button_text_en') }}</label><input type="text" name="button_text_en" value="{{ $section->button_text_en ?? '' }}"></div>
      <div class="form-group"><label>{{ t('admin.sections.button_text_ar') }}</label><input type="text" name="button_text_ar" value="{{ $section->button_text_ar ?? '' }}" dir="rtl"></div>
    </div>
    <div class="form-group"><label>{{ t('admin.sections.button_link') }}</label><input type="text" name="button_url" value="{{ $section->button_url ?? '' }}" placeholder="/register or https://..."></div>
  </div>

  <div id="fields-feature_grid" class="section-type-fields">
    <h3 style="font-size:14px;margin-top:8px;">{{ t('admin.sections.items_hint') }}</h3>
    @foreach ($items as $i => $item)
      <div class="form-row" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px;">
        <div class="form-group" style="max-width:90px;"><label>{{ t('admin.sections.icon') }}</label><input type="text" name="items[{{ $i }}][icon]" value="{{ $item['icon'] ?? '' }}" placeholder="🚀"></div>
        <div class="form-group"><label>{{ t('admin.sections.item_title_en') }}</label><input type="text" name="items[{{ $i }}][title_en]" value="{{ $item['title_en'] ?? '' }}"></div>
        <div class="form-group"><label>{{ t('admin.sections.item_title_ar') }}</label><input type="text" name="items[{{ $i }}][title_ar]" value="{{ $item['title_ar'] ?? '' }}" dir="rtl"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>{{ t('admin.sections.item_desc_en') }}</label><input type="text" name="items[{{ $i }}][desc_en]" value="{{ $item['desc_en'] ?? '' }}"></div>
        <div class="form-group"><label>{{ t('admin.sections.item_desc_ar') }}</label><input type="text" name="items[{{ $i }}][desc_ar]" value="{{ $item['desc_ar'] ?? '' }}" dir="rtl"></div>
      </div>
    @endforeach
  </div>

  <div id="fields-stat_row" class="section-type-fields">
    <h3 style="font-size:14px;margin-top:8px;">{{ t('admin.sections.stats_hint') }}</h3>
    @foreach ($items as $i => $item)
      <div class="form-row" style="border-top:1px solid var(--border);padding-top:12px;margin-top:4px;">
        <div class="form-group" style="max-width:120px;"><label>{{ t('common.value') }}</label><input type="text" name="items[{{ $i }}][value]" value="{{ $item['value'] ?? '' }}" placeholder="2,400+"></div>
        <div class="form-group"><label>{{ t('admin.sections.stat_label_en') }}</label><input type="text" name="items[{{ $i }}][label_en]" value="{{ $item['label_en'] ?? '' }}"></div>
        <div class="form-group"><label>{{ t('admin.sections.stat_label_ar') }}</label><input type="text" name="items[{{ $i }}][label_ar]" value="{{ $item['label_ar'] ?? '' }}" dir="rtl"></div>
      </div>
    @endforeach
  </div>

  <div class="form-row" style="margin-top:18px;">
    <div class="form-group" style="max-width:140px;"><label>{{ t('common.sort_order') }}</label><input type="number" name="sort_order" value="{{ $section->sort_order ?? 0 }}"></div>
    <div class="form-group">
      <label>&nbsp;</label>
      <label style="font-weight:400;display:flex;align-items:center;gap:6px;"><input type="checkbox" name="is_active" value="1" style="width:auto;" {{ ($section->is_active ?? true) ? 'checked' : '' }}> {{ t('admin.sections.visible_on_page') }}</label>
    </div>
  </div>

  <button type="submit" class="btn btn-primary">{{ $section ? t('common.save_changes') : t('admin.sections.create_section') }}</button>
</form>

<script>
(function () {
  var select = document.getElementById('section-type');
  var subtitleTextEn = {!! json_encode(t('admin.sections.subtitle_en')) !!};
  var subtitleTextAr = {!! json_encode(t('admin.sections.subtitle_ar')) !!};
  var bodyTextEn = {!! json_encode(t('admin.sections.body_text_en')) !!};
  var bodyTextAr = {!! json_encode(t('admin.sections.body_text_ar')) !!};
  function update() {
    document.querySelectorAll('.section-type-fields').forEach(function (el) { el.style.display = 'none'; });
    var active = document.getElementById('fields-' + select.value);
    if (active) active.style.display = '';
    var isCta = select.value === 'cta_banner';
    document.getElementById('subtitle-label-en').textContent = isCta ? bodyTextEn : subtitleTextEn;
    document.getElementById('subtitle-label-ar').textContent = isCta ? bodyTextAr : subtitleTextAr;
  }
  select.addEventListener('change', update);
  update();
})();
</script>
@endsection
