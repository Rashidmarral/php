@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'branding'])

<p class="help-text" style="max-width:680px;margin-top:-8px;margin-bottom:20px;"><?= t('admin.settings.branding_intro') ?></p>

<div class="grid grid-2" style="max-width:900px;align-items:start;gap:24px;">
  <form method="post" action="/admin/settings/branding" class="card">
    <?= csrf_field() ?>
    <h3 style="font-size:14px;"><?= t('admin.settings.brand_colors_title') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.settings.color_primary') ?></label>
        <input type="color" name="theme_brand" value="<?= e($colors['theme_brand']) ?>" style="height:44px;padding:4px;">
      </div>
      <div class="form-group">
        <label><?= t('admin.settings.color_primary_dark') ?></label>
        <input type="color" name="theme_brand_dark" value="<?= e($colors['theme_brand_dark']) ?>" style="height:44px;padding:4px;">
      </div>
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.color_primary_light') ?></label>
      <input type="color" name="theme_brand_light" value="<?= e($colors['theme_brand_light']) ?>" style="height:44px;padding:4px;max-width:200px;">
    </div>

    <h3 style="font-size:14px;margin-top:22px;"><?= t('admin.settings.accent_color_title') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.settings.color_accent') ?></label>
        <input type="color" name="theme_accent" value="<?= e($colors['theme_accent']) ?>" style="height:44px;padding:4px;">
      </div>
      <div class="form-group">
        <label><?= t('admin.settings.color_accent_dark') ?></label>
        <input type="color" name="theme_accent_dark" value="<?= e($colors['theme_accent_dark']) ?>" style="height:44px;padding:4px;">
      </div>
    </div>

    <div style="display:flex;gap:10px;margin-top:8px;">
      <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
      <button type="submit" name="reset" value="1" class="btn btn-light" onclick="return confirm('<?= t('admin.settings.reset_colors_confirm') ?>');"><?= t('admin.settings.reset_to_default') ?></button>
    </div>
  </form>

  <div class="card">
    <h3 style="font-size:14px;"><?= t('admin.settings.preview_title') ?></h3>
    <p class="help-text"><?= t('admin.settings.preview_live_hint') ?></p>
    <div id="theme-preview" style="border:1px solid var(--border);border-radius:10px;overflow:hidden;">
      <div id="preview-header" style="padding:14px 16px;color:#fff;font-weight:700;"><?= t('admin.settings.preview_sidebar_header') ?></div>
      <div style="padding:16px;background:#fff;display:flex;flex-direction:column;gap:10px;">
        <button type="button" id="preview-btn" style="border:none;padding:10px 16px;border-radius:8px;color:#fff;font-weight:600;cursor:default;"><?= t('admin.settings.preview_primary_button') ?></button>
        <span id="preview-badge" style="display:inline-block;width:fit-content;padding:4px 12px;border-radius:20px;color:#fff;font-size:12px;font-weight:700;"><?= t('admin.settings.preview_badge_text') ?></span>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  const brand = document.querySelector('[name=theme_brand]');
  const brandDark = document.querySelector('[name=theme_brand_dark]');
  const accent = document.querySelector('[name=theme_accent]');
  const header = document.getElementById('preview-header');
  const btn = document.getElementById('preview-btn');
  const badge = document.getElementById('preview-badge');
  function update() {
    header.style.background = 'linear-gradient(135deg,' + brand.value + ',' + brandDark.value + ')';
    btn.style.background = accent.value;
    badge.style.background = brand.value;
  }
  [brand, brandDark, accent].forEach(el => el.addEventListener('input', update));
  update();
})();
</script>
@endsection
