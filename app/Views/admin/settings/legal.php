<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/settings"><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/payments"><?= t('admin.settings.tab_payments') ?></a>
  <a href="/admin/settings/legal" class="active"><?= t('admin.settings.tab_legal') ?></a>
  <a href="/admin/settings/header"><?= t('admin.settings.tab_header') ?></a>
  <a href="/admin/settings/ai"><?= t('admin.settings.tab_ai') ?></a>
  <a href="/admin/settings/notifications"><?= t('admin.settings.tab_notifications') ?></a>
  <a href="/admin/settings/email"><?= t('admin.settings.tab_email') ?></a>
</div>

<p class="help-text" style="max-width:680px;margin-bottom:16px;">
  This is BuildXact Saudi's own legal identity as the platform operator — shown in the public
  website footer (VAT/CR) and used for the platform's own brand logo. This is separate from any
  subscriber company's profile, which they manage from their own Settings page (or you can edit on
  their behalf from Companies → a company → Edit company profile).
</p>

<form method="post" action="/admin/settings/legal" enctype="multipart/form-data" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>

  <h3 style="font-size:14px;"><?= t('admin.settings.brand') ?></h3>
  <div class="form-group">
    <label><?= t('admin.settings.platform_logo') ?></label>
    <?php if (!empty($settings['platform_logo_path'])): ?>
      <div style="margin-bottom:8px;"><img src="<?= View::e($settings['platform_logo_path']) ?>" alt="Logo" style="height:56px;border-radius:8px;border:1px solid var(--border);"></div>
    <?php endif; ?>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
    <p class="help-text">Replaces the "BX" mark in the site header/footer once uploaded. JPG, PNG, or WEBP, up to 3MB.</p>
  </div>

  <h3 style="font-size:14px;margin-top:20px;"><?= t('admin.settings.legal_entity') ?></h3>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.legal_name_en') ?></label><input type="text" name="platform_legal_name_en" value="<?= View::e($settings['platform_legal_name_en'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.legal_name_ar') ?></label><input type="text" name="platform_legal_name_ar" dir="rtl" value="<?= View::e($settings['platform_legal_name_ar'] ?? '') ?>" placeholder="الاسم النظامي بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.cr_number') ?></label><input type="text" name="platform_cr_number" value="<?= View::e($settings['platform_cr_number'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.vat_number') ?></label><input type="text" name="platform_vat_number" value="<?= View::e($settings['platform_vat_number'] ?? '') ?>"></div>
  </div>

  <h3 style="font-size:14px;margin-top:20px;"><?= t('admin.settings.registered_address') ?></h3>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.building_number') ?></label><input type="text" name="platform_building_number" maxlength="4" value="<?= View::e($settings['platform_building_number'] ?? '') ?>" placeholder="1234"></div>
    <div class="form-group"><label><?= t('admin.settings.street_name') ?></label><input type="text" name="platform_street_name" value="<?= View::e($settings['platform_street_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.district') ?></label><input type="text" name="platform_district" value="<?= View::e($settings['platform_district'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.city') ?></label><input type="text" name="platform_city" value="<?= View::e($settings['platform_city'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.postal_code') ?></label><input type="text" name="platform_postal_code" maxlength="5" value="<?= View::e($settings['platform_postal_code'] ?? '') ?>" placeholder="12345"></div>
    <div class="form-group"><label><?= t('admin.settings.additional_number') ?></label><input type="text" name="platform_additional_number" maxlength="4" value="<?= View::e($settings['platform_additional_number'] ?? '') ?>" placeholder="6789"></div>
  </div>

  <h3 style="font-size:14px;margin-top:20px;"><?= t('admin.settings.legal_documents') ?></h3>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.cr_certificate') ?></label>
      <?php if (!empty($settings['platform_cr_document_path'])): ?>
        <p class="help-text"><a href="<?= View::e($settings['platform_cr_document_path']) ?>" target="_blank" rel="noopener"><?= t('admin.settings.view_current_file') ?></a></p>
      <?php endif; ?>
      <input type="file" name="cr_document" accept="application/pdf,image/png,image/jpeg">
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.vat_certificate') ?></label>
      <?php if (!empty($settings['platform_vat_document_path'])): ?>
        <p class="help-text"><a href="<?= View::e($settings['platform_vat_document_path']) ?>" target="_blank" rel="noopener"><?= t('admin.settings.view_current_file') ?></a></p>
      <?php endif; ?>
      <input type="file" name="vat_document" accept="application/pdf,image/png,image/jpeg">
    </div>
  </div>
  <p class="help-text">PDF, JPG, or PNG, up to 10MB each. Kept on file for your own records — not shown publicly.</p>

  <button type="submit" class="btn btn-primary" style="margin-top:8px;"><?= t('common.save') ?></button>
</form>
