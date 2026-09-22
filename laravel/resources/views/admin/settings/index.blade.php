@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'general'])

<form method="post" action="/admin/settings" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div class="form-group">
    <label><?= t('admin.settings.platform_url') ?></label>
    <input type="text" value="<?= e(config('app.url')) ?>" disabled>
    <p class="help-text"><?= t('admin.settings.platform_url_hint') ?></p>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.site_name') ?></label><input type="text" name="site_name" value="<?= e($settings['site_name'] ?? 'BuildXact Saudi') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.vat_rate') ?></label><input type="number" step="0.01" name="vat_rate" value="<?= e($settings['vat_rate'] ?? '15') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.support_email') ?></label><input type="email" name="support_email" value="<?= e($settings['support_email'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.support_phone') ?></label><input type="tel" name="support_phone" value="<?= e($settings['support_phone'] ?? '') ?>"></div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.default_country') ?></label>
      <select name="default_country">
        <?php foreach (\App\Http\Controllers\Admin\SiteSettingsController::COUNTRIES as $code => $label): ?>
          <option value="<?= e($code) ?>" <?= ($settings['default_country'] ?? 'SA') === $code ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.default_timezone') ?></label>
      <select name="default_timezone">
        <?php foreach (\App\Http\Controllers\Admin\SiteSettingsController::TIMEZONES as $tz): ?>
          <option value="<?= e($tz) ?>" <?= ($settings['default_timezone'] ?? 'Asia/Riyadh') === $tz ? 'selected' : '' ?>><?= e($tz) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.default_language') ?></label>
      <select name="default_language">
        <option value="en" <?= ($settings['default_language'] ?? 'en') === 'en' ? 'selected' : '' ?>><?= t('admin.settings.language_en') ?></option>
        <option value="ar" <?= ($settings['default_language'] ?? 'en') === 'ar' ? 'selected' : '' ?>><?= t('admin.settings.language_ar') ?></option>
      </select>
      <p class="help-text"><?= t('admin.settings.default_language_hint') ?></p>
    </div>
    <div class="form-group" id="currency">
      <label><?= t('admin.settings.currency') ?></label>
      <select name="currency">
        <?php foreach (\App\Http\Controllers\Admin\SiteSettingsController::CURRENCIES as $code): ?>
          <option value="<?= e($code) ?>" <?= ($settings['currency'] ?? 'SAR') === $code ? 'selected' : '' ?>><?= e($code) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help-text"><?= t('admin.settings.currency_hint') ?></p>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.date_format') ?></label>
      <select name="date_format">
        <?php foreach (\App\Http\Controllers\Admin\SiteSettingsController::DATE_FORMATS as $format): ?>
          <option value="<?= e($format) ?>" <?= ($settings['date_format'] ?? 'd/m/Y') === $format ? 'selected' : '' ?>><?= e($format) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.time_format') ?></label>
      <select name="time_format">
        <option value="24" <?= ($settings['time_format'] ?? '24') === '24' ? 'selected' : '' ?>><?= t('admin.settings.time_format_24') ?></option>
        <option value="12" <?= ($settings['time_format'] ?? '24') === '12' ? 'selected' : '' ?>><?= t('admin.settings.time_format_12') ?></option>
      </select>
    </div>
  </div>
  <div class="form-group" style="max-width:328px;">
    <label><?= t('admin.settings.fiscal_year_start') ?></label>
    <select name="fiscal_year_start">
      <?php foreach (\App\Http\Controllers\Admin\SiteSettingsController::MONTHS as $number => $label): ?>
        <option value="<?= $number ?>" <?= (int) ($settings['fiscal_year_start'] ?? 1) === $number ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <p class="help-text"><?= t('admin.settings.fiscal_year_start_hint') ?></p>
  </div>

  <div class="form-row" style="margin-top:8px;">
    <label style="font-weight:400;"><input type="checkbox" name="allow_new_registrations" value="1" style="width:auto;display:inline-block;" <?= ($settings['allow_new_registrations'] ?? '1') !== '0' ? 'checked' : '' ?>> <?= t('admin.settings.allow_new_registrations') ?></label>
  </div>
  <p class="help-text" style="margin-top:2px;"><?= t('admin.settings.allow_new_registrations_hint') ?></p>
  <div class="form-row">
    <label style="font-weight:400;"><input type="checkbox" name="allow_demo_accounts" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['allow_demo_accounts']) ? 'checked' : '' ?>> <?= t('admin.settings.allow_demo_accounts') ?></label>
  </div>
  <p class="help-text" style="margin-top:2px;"><?= t('admin.settings.allow_demo_accounts_hint') ?></p>

  <button type="submit" class="btn btn-primary" style="margin-top:12px;"><?= t('admin.settings.save_settings') ?></button>
</form>

<div class="card" style="max-width:680px;margin-top:20px;">
  <h3><?= t('admin.settings.what_this_controls') ?></h3>
  <p class="help-text">
    Trial length now lives on the Signup tab. VAT rate is used on the Quick Estimate calculator and
    can be reflected on new invoices going forward. Date format is wired into a couple of
    business-facing summary pages today (Company → Integrations and Admin → Tenders) — adopting it
    everywhere a date is shown across the app is a follow-up, not done here. Time format is stored
    for future use; no business-facing time display exists yet to plug it into.
  </p>
</div>

@endsection
