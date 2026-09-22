@extends('layouts.app')

@section('content')
<?php $ro = auth()->user()->isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'profile'])

<form method="post" action="/app/settings" enctype="multipart/form-data" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;"><?= t('user.settings.company_profile') ?></h3>
  <div class="form-group">
    <label><?= t('user.settings.company_logo') ?></label>
    <?php if (!empty($company['logo_path'])): ?>
      <div style="margin-bottom:8px;"><img src="<?= e($company['logo_path']) ?>" alt="<?= t('user.settings.logo_alt') ?>" style="height:56px;border-radius:8px;border:1px solid var(--border);"></div>
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
  <div class="form-group"><label><?= t('user.settings.address_freetext') ?></label><input type="text" name="address" value="<?= e($company['address'] ?? '') ?>" placeholder="<?= t('user.settings.address_placeholder') ?>" <?= $ro ?>></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.company.cr_number') ?></label><input type="text" name="cr_number" value="<?= e($company['cr_number']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label><?= t('common.tax_number') ?></label><input type="text" name="vat_number" value="<?= e($company['vat_number']) ?>" <?= $ro ?>></div>
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
    <div class="form-group"><label><?= t('user.settings.country') ?></label><input type="text" value="<?= t('common.saudi_arabia') ?>" disabled></div>
  </div>

  <?php if (auth()->user()->isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.owner_only_hint') ?></p>
  <?php endif; ?>
</form>

@endsection
