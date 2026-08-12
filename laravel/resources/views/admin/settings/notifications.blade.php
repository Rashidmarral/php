@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/settings"><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/payments"><?= t('admin.settings.tab_payments') ?></a>
  <a href="/admin/settings/legal"><?= t('admin.settings.tab_legal') ?></a>
  <a href="/admin/settings/header"><?= t('admin.settings.tab_header') ?></a>
  <a href="/admin/settings/ai"><?= t('admin.settings.tab_ai') ?></a>
  <a href="/admin/settings/notifications" class="active"><?= t('admin.settings.tab_notifications') ?></a>
  <a href="/admin/settings/email"><?= t('admin.settings.tab_email') ?></a>
  <a href="/admin/settings/theme"><?= t('admin.settings.tab_theme') ?></a>
</div>

<div class="card" style="max-width:680px;margin-bottom:20px;">
  <h3 style="font-size:14px;">💬 "Send via WhatsApp" links — always on, no setup</h3>
  <p class="help-text">
    Every invoice and estimate already has a "Send via WhatsApp" button that opens a pre-filled
    WhatsApp chat with the client (a <code>wa.me</code> link) — this needs no account or API key
    and works for every company automatically. The setup below is only for fully automated,
    no-click WhatsApp notifications (e.g. auto-notifying a client the moment an invoice is
    created).
  </p>
</div>

<form method="post" action="/admin/settings/notifications" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
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
  <button type="submit" class="btn btn-primary"><?= t('common.save') ?></button>
</form>

@endsection
