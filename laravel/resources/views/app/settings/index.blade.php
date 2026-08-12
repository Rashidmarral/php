@extends('layouts.app')

@section('content')
<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; $ro = auth()->user()->isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

<form method="post" action="/app/settings" enctype="multipart/form-data" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;"><?= t('user.settings.company_profile') ?></h3>
  <div class="form-group">
    <label><?= t('user.settings.company_logo') ?></label>
    <?php if (!empty($company['logo_path'])): ?>
      <div style="margin-bottom:8px;"><img src="<?= e($company['logo_path']) ?>" alt="Logo" style="height:56px;border-radius:8px;border:1px solid var(--border);"></div>
    <?php endif; ?>
    <?php if (auth()->user()->isCompanyOwner()): ?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.company_name_en') ?></label><input type="text" name="name" value="<?= e($company['name']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('user.settings.company_name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($company['name_ar'] ?? '') ?>" placeholder="اسم الشركة" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" value="<?= e($company['phone']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('common.city') ?></label><input type="text" name="city" value="<?= e($company['city']) ?>" <?= $ro ?>></div>
  </div>
  <div class="form-group"><label><?= t('user.settings.address_freetext') ?></label><input type="text" name="address" value="<?= e($company['address'] ?? '') ?>" placeholder="Street, district" <?= $ro ?>></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.company.cr_number') ?></label><input type="text" name="cr_number" value="<?= e($company['cr_number']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('common.tax_number') ?></label><input type="text" name="vat_number" value="<?= e($company['vat_number']) ?>" <?= $ro ?>></div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.settings.legal_documents') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.legal_docs_hint') ?></p>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.cr_certificate') ?></label>
      <?php if (!empty($company['cr_document_path'])): ?>
        <p class="help-text"><a href="<?= e($company['cr_document_path']) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
      <?php endif; ?>
      <?php if (auth()->user()->isCompanyOwner()): ?><input type="file" name="cr_document" accept="application/pdf,image/png,image/jpeg"><?php endif; ?>
    </div>
    <div class="form-group">
      <label><?= t('user.settings.vat_certificate') ?></label>
      <?php if (!empty($company['vat_document_path'])): ?>
        <p class="help-text"><a href="<?= e($company['vat_document_path']) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
      <?php endif; ?>
      <?php if (auth()->user()->isCompanyOwner()): ?><input type="file" name="vat_document" accept="application/pdf,image/png,image/jpeg"><?php endif; ?>
    </div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.settings.classification') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.classification_hint') ?></p>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.classification_grade') ?></label>
      <select name="contractor_classification" <?= $ro ?>>
        <option value=""><?= t('user.settings.not_classified') ?></option>
        <?php foreach (['1'=>'Grade 1','2'=>'Grade 2','3'=>'Grade 3','4'=>'Grade 4','5'=>'Grade 5'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($company['contractor_classification'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.settings.classification_number') ?></label><input type="text" name="contractor_classification_number" value="<?= e($company['contractor_classification_number'] ?? '') ?>" <?= $ro ?>></div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.settings.zatca_address') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.zatca_address_hint') ?></p>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.building_number') ?></label><input type="text" name="building_number" maxlength="4" value="<?= e($company['building_number'] ?? '') ?>" placeholder="1234" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('user.settings.street_name') ?></label><input type="text" name="street_name" value="<?= e($company['street_name'] ?? '') ?>" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.district') ?></label><input type="text" name="district" value="<?= e($company['district'] ?? '') ?>" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('user.settings.postal_code') ?></label><input type="text" name="postal_code" maxlength="5" value="<?= e($company['postal_code'] ?? '') ?>" placeholder="12345" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.additional_number') ?></label><input type="text" name="additional_number" maxlength="4" value="<?= e($company['additional_number'] ?? '') ?>" placeholder="6789" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('user.settings.country') ?></label><input type="text" value="Saudi Arabia" disabled></div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.settings.business_controls') ?></h3>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.default_markup') ?></label>
      <input type="number" step="0.01" name="default_markup_percent" value="<?= e((string)($company['default_markup_percent'] ?? 0)) ?>" <?= $ro ?>>
      <p class="help-text"><?= t('user.settings.default_markup_hint') ?></p>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="client_portal_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($company['client_portal_enabled']) ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.enable_client_portal') ?></label>
      <p class="help-text"><?= t('user.settings.client_portal_hint') ?></p>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.default_retention') ?></label>
      <input type="number" step="0.01" name="default_retention_percent" value="<?= e((string)($company['default_retention_percent'] ?? 0)) ?>" <?= $ro ?>>
      <p class="help-text"><?= t('user.settings.default_retention_hint') ?></p>
    </div>
  </div>

  <?php if (auth()->user()->isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.owner_only_hint') ?></p>
  <?php endif; ?>
</form>

<form method="post" action="/app/settings/password" class="card" style="max-width:680px;margin-top:24px;">
  <?= csrf_field() ?>
  <h3 style="font-size:14px;"><?= t('user.settings.change_password') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.change_password_hint') ?></p>
  <div class="form-group">
    <label><?= t('common.current_password') ?></label>
    <div class="password-field">
      <input type="password" name="current_password" required autocomplete="current-password">
      <?= passwordToggle() ?>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.new_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <?= passwordToggle() ?>
      </div>
    </div>
    <div class="form-group">
      <label><?= t('user.settings.confirm_new_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
        <?= passwordToggle() ?>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('user.settings.update_password') ?></button>
</form>

@endsection
