@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/settings" class="active"><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/payments"><?= t('admin.settings.tab_payments') ?></a>
  <a href="/admin/settings/legal"><?= t('admin.settings.tab_legal') ?></a>
  <a href="/admin/settings/header"><?= t('admin.settings.tab_header') ?></a>
  <a href="/admin/settings/ai"><?= t('admin.settings.tab_ai') ?></a>
  <a href="/admin/settings/notifications"><?= t('admin.settings.tab_notifications') ?></a>
  <a href="/admin/settings/email"><?= t('admin.settings.tab_email') ?></a>
  <a href="/admin/settings/theme"><?= t('admin.settings.tab_theme') ?></a>
</div>

<form method="post" action="/admin/settings" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.trial_days') ?></label>
      <input type="number" name="trial_days" min="0" value="<?= e($settings['trial_days'] ?? '14') ?>">
      <p class="help-text"><?= t('admin.settings.trial_days_hint') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.vat_rate') ?></label>
      <input type="number" step="0.01" name="vat_rate" value="<?= e($settings['vat_rate'] ?? '15') ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.site_name') ?></label><input type="text" name="site_name" value="<?= e($settings['site_name'] ?? 'BuildXact Saudi') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.currency') ?></label><input type="text" name="currency" value="<?= e($settings['currency'] ?? 'SAR') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.support_email') ?></label><input type="email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.support_phone') ?></label><input type="tel" name="support_phone" value="<?= e($settings['support_phone'] ?? '') ?>"></div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('admin.settings.save_settings') ?></button>
</form>

<div class="card" style="max-width:680px;margin-top:20px;">
  <h3><?= t('admin.settings.what_this_controls') ?></h3>
  <p class="help-text">
    Trial length applies to every new company that signs up from the pricing page from now on
    (existing trials already in progress keep their original end date). VAT rate is used on the
    Quick Estimate calculator and can be reflected on new invoices going forward.
  </p>
</div>

@endsection
