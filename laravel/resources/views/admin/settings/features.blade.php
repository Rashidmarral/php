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
  <p class="help-text">
    Every company can already describe a project in plain language on the Create Estimate screen
    and get a draft estimate back — without any setup here — by matching the description against
    the built-in template library. Configuring a real API key below upgrades this to a genuine,
    tailored AI-written estimate instead of the closest matching template.
  </p>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 Anthropic Claude API</h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="ai_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['ai_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">
    Get an API key from the <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">Anthropic Console</a>.
    Usage is billed to that account per the API's standard token pricing.
  </p>
  <div class="form-group">
    <label><?= t('admin.settings.model') ?></label>
    <input type="text" name="ai_model" value="<?= e($settings['ai_model'] ?? 'claude-sonnet-5') ?>" placeholder="claude-sonnet-5">
    <p class="help-text"><?= t('admin.settings.model_hint') ?></p>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.api_key') ?></label>
    <div class="password-field">
      <input type="password" name="ai_api_key" placeholder="<?= !empty($settings['ai_api_key']) ? 'Saved — leave blank to keep it' : 'sk-ant-...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>
  <?php if (!empty($settings['ai_last_error'])): ?>
    <div class="alert alert-error"><?= t('admin.settings.last_api_error') ?> <?= e($settings['ai_last_error']) ?></div>
  <?php endif; ?>

  <h3 style="font-size:14px;margin-top:26px;">💬 <?= t('admin.settings.whatsapp_links_free') ?></h3>
  <p class="help-text">
    Every invoice and estimate already has a "Send via WhatsApp" button that opens a pre-filled
    WhatsApp chat with the client (a <code>wa.me</code> link) — this needs no account or API key
    and works for every company automatically. The setup below is only for fully automated,
    no-click WhatsApp notifications (e.g. auto-notifying a client the moment an invoice is
    created).
  </p>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 <?= t('admin.settings.automated_whatsapp') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="whatsapp_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['whatsapp_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">
    Requires a real <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener">Meta WhatsApp Business Cloud API</a>
    account — a phone number registered on the WhatsApp Business Platform and an access token from
    your Meta App. Outside a client's 24-hour service window, Meta also requires pre-approved
    message templates rather than free-form text; this integration sends free-form messages, so it
    works best for time-sensitive notifications sent shortly after a client last messaged you.
  </p>
  <div class="form-group">
    <label><?= t('admin.settings.phone_number_id') ?></label>
    <input type="text" name="whatsapp_phone_number_id" value="<?= e($settings['whatsapp_phone_number_id'] ?? '') ?>" placeholder="e.g. 109876543210123">
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.access_token') ?></label>
    <div class="password-field">
      <input type="password" name="whatsapp_access_token" placeholder="<?= !empty($settings['whatsapp_access_token']) ? '••••••••••••••••  (leave blank to keep current)' : 'EAAG...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>

  <h3 style="font-size:14px;margin-top:26px;">📱 <?= t('admin.settings.sms_no_free_fallback') ?></h3>
  <p class="help-text">
    Unlike WhatsApp, SMS has no free "share link" option — every SMS goes through a real gateway
    account. This integration targets
    <a href="https://docs.unifonic.com/reference/messaging-1" target="_blank" rel="noopener">Unifonic's REST SMS API</a>,
    one of the most widely used SMS gateways for Saudi/GCC businesses. You'll need an <code>AppSid</code>
    credential and an approved alphanumeric <code>SenderID</code> from your Unifonic account.
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
      <input type="password" name="sms_app_sid" placeholder="<?= !empty($settings['sms_app_sid']) ? '••••••••••••••••  (leave blank to keep current)' : 'e.g. 9dyO1nT7...' ?>">
      <?= passwordToggle() ?>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:12px;"><?= t('common.save_changes') ?></button>
</form>

@endsection
