@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'features'])

<p class="help-text" style="max-width:680px;margin-top:-8px;margin-bottom:20px;"><?= t('admin.settings.features_intro') ?></p>

<form method="post" action="/admin/settings/features" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;">✨ <?= t('admin.settings.ai_estimate_generator') ?></h3>
  <p class="help-text"><?= t('admin.settings.ai_estimate_generator_hint') ?></p>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 <?= t('admin.settings.anthropic_api_title') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="ai_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['ai_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">
    <?= t('admin.settings.anthropic_api_key_before') ?> <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener"><?= t('admin.settings.anthropic_console_link') ?></a>.
    <?= t('admin.settings.anthropic_api_key_after') ?>
  </p>
  <div class="form-group">
    <label><?= t('admin.settings.model') ?></label>
    <input type="text" name="ai_model" value="<?= e($settings['ai_model'] ?? 'claude-sonnet-5') ?>" placeholder="claude-sonnet-5">
    <p class="help-text"><?= t('admin.settings.model_hint') ?></p>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.api_key') ?></label>
    <div class="password-field">
      <input type="password" name="ai_api_key" placeholder="<?= !empty($settings['ai_api_key']) ? t('admin.settings.saved_leave_blank') : 'sk-ant-...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>
  <?php if (!empty($settings['ai_last_error'])): ?>
    <div class="alert alert-error"><?= t('admin.settings.last_api_error') ?> <?= e($settings['ai_last_error']) ?></div>
  <?php endif; ?>

  <h3 style="font-size:14px;margin-top:26px;">💬 <?= t('admin.settings.whatsapp_links_free') ?></h3>
  <p class="help-text"><?= t('admin.settings.whatsapp_links_free_hint') ?></p>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 <?= t('admin.settings.automated_whatsapp') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="whatsapp_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['whatsapp_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">
    <?= t('admin.settings.whatsapp_api_before') ?> <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener"><?= t('admin.settings.whatsapp_api_link') ?></a>
    <?= t('admin.settings.whatsapp_api_after') ?>
  </p>
  <div class="form-group">
    <label><?= t('admin.settings.phone_number_id') ?></label>
    <input type="text" name="whatsapp_phone_number_id" value="<?= e($settings['whatsapp_phone_number_id'] ?? '') ?>" placeholder="e.g. 109876543210123">
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.access_token') ?></label>
    <div class="password-field">
      <input type="password" name="whatsapp_access_token" placeholder="<?= !empty($settings['whatsapp_access_token']) ? t('admin.settings.secret_masked_placeholder') : 'EAAG...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>

  <h3 style="font-size:14px;margin-top:26px;">📱 <?= t('admin.settings.sms_no_free_fallback') ?></h3>
  <p class="help-text">
    <?= t('admin.settings.sms_intro_before') ?>
    <a href="https://docs.unifonic.com/reference/messaging-1" target="_blank" rel="noopener"><?= t('admin.settings.unifonic_link') ?></a>,
    <?= t('admin.settings.sms_intro_after') ?> <code>AppSid</code>
    <?= t('admin.settings.sms_intro_sender_id') ?> <code>SenderID</code> <?= t('admin.settings.sms_intro_tail') ?>
  </p>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 <?= t('admin.settings.automated_sms') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="sms_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['sms_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.sms_sender_id') ?></label>
    <input type="text" name="sms_sender_id" value="<?= e($settings['sms_sender_id'] ?? '') ?>" placeholder="e.g. BuildXact">
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.sms_app_sid') ?></label>
    <div class="password-field">
      <input type="password" name="sms_app_sid" placeholder="<?= !empty($settings['sms_app_sid']) ? t('admin.settings.secret_masked_placeholder') : 'e.g. 9dyO1nT7...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:12px;"><?= t('common.save_changes') ?></button>
</form>

@endsection
