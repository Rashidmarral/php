@extends('layouts.app')

@section('content')
<?php $ro = $canManage ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'invoice_templates'])

<div class="section-head" style="text-align:start;margin-bottom:16px;display:flex;justify-content:space-between;align-items:flex-end;flex-wrap:wrap;gap:10px;">
  <div>
    <h2 style="font-size:19px;"><?= e(app()->getLocale() === 'ar' && $template->name_ar ? $template->name_ar : $template->name) ?></h2>
    <p style="color:var(--muted);font-size:14px;">
      <?= t('pdf.doctype.' . $template->document_type) ?> ·
      <?= t('user.settings.layout_label') ?>: <?= t('user.settings.layout_option_' . $template->layout) ?>
    </p>
  </div>
  <a href="/app/settings/invoice-templates/<?= e($template->document_type) ?>" class="btn btn-light btn-sm"><?= t('user.settings.back_to_gallery') ?></a>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:24px;align-items:start;">
  <form method="post" action="/app/settings/invoice-templates/template/<?= $template->id ?>" enctype="multipart/form-data" class="card" style="padding:20px;">
    <?= csrf_field() ?>

    <h3 style="font-size:14px;"><?= t('user.settings.template_identity') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.template_name') ?></label>
        <input type="text" name="name" value="<?= e($template->name) ?>" <?= $ro ?> required>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.template_name_ar') ?></label>
        <input type="text" name="name_ar" value="<?= e($template->name_ar ?? '') ?>" dir="rtl" <?= $ro ?>>
      </div>
    </div>

    <p class="help-text" style="margin:-6px 0 16px;">
      <?= t('user.settings.layout_locked_hint') ?>
    </p>

    <h3 style="font-size:14px;margin-top:20px;"><?= t('user.settings.colors_heading') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.accent_color') ?></label>
        <input type="color" name="accent_color" value="<?= e($template->accent_color ?: '#16233f') ?>" <?= $ro ?> style="height:38px;padding:2px;">
      </div>
      <div class="form-group">
        <label><?= t('user.settings.table_header_color') ?></label>
        <input type="color" name="table_header_color" value="<?= e($template->table_header_color ?: ($template->accent_color ?: '#16233f')) ?>" <?= $ro ?> style="height:38px;padding:2px;">
        <label style="font-weight:400;margin-top:4px;"><input type="checkbox" name="remove_table_header_color" value="1" style="width:auto;display:inline-block;" <?= $ro ?>> <?= t('user.settings.use_accent_instead') ?></label>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.totals_color') ?></label>
        <input type="color" name="totals_color" value="<?= e($template->totals_color ?: ($template->accent_color ?: '#16233f')) ?>" <?= $ro ?> style="height:38px;padding:2px;">
        <label style="font-weight:400;margin-top:4px;"><input type="checkbox" name="remove_totals_color" value="1" style="width:auto;display:inline-block;" <?= $ro ?>> <?= t('user.settings.use_accent_instead') ?></label>
      </div>
    </div>

    <h3 style="font-size:14px;margin-top:20px;"><?= t('user.settings.layout_options_heading') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.density') ?></label>
        <select name="density" <?= $ro ?>>
          <?php foreach ($densities as $d): ?>
            <option value="<?= $d ?>" <?= $template->density === $d ? 'selected' : '' ?>><?= t('user.settings.density_option_' . $d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.language_mode') ?></label>
        <select name="language_mode" <?= $ro ?>>
          <?php foreach ($languageModes as $m): ?>
            <option value="<?= $m ?>" <?= $template->language_mode === $m ? 'selected' : '' ?>><?= t('user.settings.language_mode_option_' . $m) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.table_direction') ?></label>
        <select name="table_direction" <?= $ro ?>>
          <?php foreach ($tableDirections as $d): ?>
            <option value="<?= $d ?>" <?= $template->table_direction === $d ? 'selected' : '' ?>><?= t('user.settings.table_direction_option_' . $d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.page_size') ?></label>
        <select name="page_size" <?= $ro ?>>
          <?php foreach ($pageSizes as $p): ?>
            <option value="<?= $p ?>" <?= $template->page_size === $p ? 'selected' : '' ?>><?= t('user.settings.page_size_option_' . $p) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3 style="font-size:14px;margin-top:20px;"><?= t('user.settings.visibility_heading') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_logo" value="1" style="width:auto;display:inline-block;" <?= $template->show_logo ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.show_logo') ?></label></div>
      <div class="form-group"><label><input type="checkbox" name="show_unit_labels" value="1" style="width:auto;display:inline-block;" <?= $template->show_unit_labels ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.show_unit_labels') ?></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_party_vat_number" value="1" style="width:auto;display:inline-block;" <?= $template->show_party_vat_number ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.show_party_vat_number') ?></label></div>
      <div class="form-group"><label><input type="checkbox" name="show_item_description" value="1" style="width:auto;display:inline-block;" <?= $template->show_item_description ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.show_item_description') ?></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_vat_column" value="1" style="width:auto;display:inline-block;" <?= $template->show_vat_column ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.show_vat_column') ?></label></div>
    </div>

    <h3 style="font-size:14px;margin-top:20px;"><?= t('user.settings.branding_images_heading') ?></h3>
    <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.branding_images_hint') ?></p>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.letterhead_image') ?></label>
        <?php if ($template->letterhead_path): ?>
          <p class="help-text"><a href="<?= e($template->letterhead_path) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
          <label style="font-weight:400;"><input type="checkbox" name="remove_letterhead" value="1" style="width:auto;display:inline-block;" <?= $ro ?>> <?= t('common.remove') ?></label>
        <?php endif; ?>
        <?php if ($canManage): ?><input type="file" name="letterhead" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.footer_image') ?></label>
        <?php if ($template->footer_path): ?>
          <p class="help-text"><a href="<?= e($template->footer_path) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
          <label style="font-weight:400;"><input type="checkbox" name="remove_footer" value="1" style="width:auto;display:inline-block;" <?= $ro ?>> <?= t('common.remove') ?></label>
        <?php endif; ?>
        <?php if ($canManage): ?><input type="file" name="footer" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.watermark_image') ?></label>
        <?php if ($template->watermark_path): ?>
          <p class="help-text"><a href="<?= e($template->watermark_path) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
          <label style="font-weight:400;"><input type="checkbox" name="remove_watermark" value="1" style="width:auto;display:inline-block;" <?= $ro ?>> <?= t('common.remove') ?></label>
        <?php endif; ?>
        <?php if ($canManage): ?><input type="file" name="watermark" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.watermark_opacity') ?></label>
        <input type="number" name="watermark_opacity" min="1" max="100" value="<?= (int) $template->watermark_opacity ?>" <?= $ro ?>>
      </div>
    </div>

    <h3 style="font-size:14px;margin-top:20px;"><?= t('user.settings.notes_terms_heading') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.notes_en') ?></label>
        <textarea name="notes_en" rows="3" <?= $ro ?>><?= e($template->notes_en ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.notes_ar') ?></label>
        <textarea name="notes_ar" rows="3" dir="rtl" <?= $ro ?>><?= e($template->notes_ar ?? '') ?></textarea>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.settings.terms_en') ?></label>
        <textarea name="terms_en" rows="4" <?= $ro ?>><?= e($template->terms_en ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label><?= t('user.settings.terms_ar') ?></label>
        <textarea name="terms_ar" rows="4" dir="rtl" <?= $ro ?>><?= e($template->terms_ar ?? '') ?></textarea>
      </div>
    </div>

    <?php if ($canManage): ?>
      <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
    <?php else: ?>
      <p class="help-text"><?= t('user.settings.manage_settings_required_hint') ?></p>
    <?php endif; ?>
  </form>

  <div>
    <div class="card" style="padding:14px;position:sticky;top:16px;">
      <h3 style="font-size:13px;margin:0 0 4px;"><?= t('user.settings.live_preview_heading') ?></h3>
      <p class="help-text" style="margin-top:0;margin-bottom:10px;"><?= t('user.settings.live_preview_hint') ?></p>
      <div style="width:100%;aspect-ratio:210/297;overflow:hidden;border:1px solid var(--border);border-radius:8px;position:relative;background:#fff;">
        <iframe srcdoc="<?= e($preview) ?>" style="width:794px;height:1123px;border:0;position:absolute;top:0;left:0;transform:scale(0.36);transform-origin:top left;" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
      </div>
    </div>

    <?php if ($canManage): ?>
      <form method="post" action="/app/settings/invoice-templates/template/<?= $template->id ?>/delete" style="margin-top:12px;" onsubmit="return confirm('<?= t('user.settings.delete_template_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-light" style="width:100%;color:var(--danger);"><?= t('user.settings.delete_this_template') ?></button>
      </form>
      <?php if (!$template->is_default): ?>
        <form method="post" action="/app/settings/invoice-templates/template/<?= $template->id ?>/default" style="margin-top:8px;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline" style="width:100%;"><?= t('user.settings.set_default') ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

@endsection
