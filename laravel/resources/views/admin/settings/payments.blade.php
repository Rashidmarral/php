@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'payments'])
<p class="help-text" style="max-width:680px;margin-top:-8px;margin-bottom:16px;"><?= t('admin.settings.payments_reached_via_quick_link') ?></p>

<form method="post" action="/admin/settings/payments" class="card" style="max-width:680px;margin-bottom:20px;">
  <?= csrf_field() ?>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🏦 <?= t('admin.settings.bank_transfer') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="bank_transfer_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['bank_transfer_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">Companies see these details when they choose "Bank Transfer" to pay for a subscription. Payments are held as pending until you approve them from Admin → Payments.</p>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.bank_name') ?></label><input type="text" name="bank_name" value="<?= e($settings['bank_name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.account_name') ?></label><input type="text" name="bank_account_name" value="<?= e($settings['bank_account_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.iban') ?></label><input type="text" name="bank_iban" value="<?= e($settings['bank_iban'] ?? '') ?>" placeholder="SA00 0000 0000 0000 0000 0000"></div>
    <div class="form-group"><label><?= t('admin.settings.account_number') ?></label><input type="text" name="bank_account_number" value="<?= e($settings['bank_account_number'] ?? '') ?>"></div>
  </div>

  <h3 style="margin-top:24px;display:flex;justify-content:space-between;align-items:center;">
    💳 Moyasar (mada / Visa / Mastercard / Apple Pay / STC Pay)
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="moyasar_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['moyasar_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </h3>
  <p class="help-text">
    <a href="https://moyasar.com" target="_blank" rel="noopener">Moyasar</a> is a Saudi payment
    gateway supporting mada natively alongside Visa/Mastercard, Apple Pay, and STC Pay — the
    broadest single-provider coverage for Saudi customers. Get your API keys from the Moyasar
    dashboard. Card payments stay disabled site-wide until both keys are set and this is enabled.
  </p>
  <div class="form-group"><label><?= t('admin.settings.publishable_key') ?></label><input type="text" name="moyasar_publishable_key" value="<?= e($settings['moyasar_publishable_key'] ?? '') ?>" placeholder="pk_live_..."></div>
  <div class="form-group">
    <label><?= t('admin.settings.secret_key') ?></label>
    <div class="password-field">
      <input type="password" name="moyasar_secret_key" placeholder="<?= !empty($settings['moyasar_secret_key']) ? '••••••••••••••••  (leave blank to keep current)' : 'sk_live_...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><?= t('admin.settings.save_payment_settings') ?></button>
</form>

@endsection
