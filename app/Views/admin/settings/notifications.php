<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Platform Settings</h1>
</div>

<div class="tabs">
  <a href="/admin/settings">General</a>
  <a href="/admin/settings/payments">Payment Methods</a>
  <a href="/admin/settings/legal">Legal & Branding</a>
  <a href="/admin/settings/header">Header & Footer</a>
  <a href="/admin/settings/notifications" class="active">Notifications</a>
  <a href="/admin/settings/email">Email</a>
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
  <?= Csrf::field() ?>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🤖 Automated WhatsApp notifications</h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="whatsapp_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['whatsapp_enabled']) ? 'checked' : '' ?>> Enabled</label>
  </div>
  <p class="help-text">
    Requires a real <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener">Meta WhatsApp Business Cloud API</a>
    account — a phone number registered on the WhatsApp Business Platform and an access token from
    your Meta App. Outside a client's 24-hour service window, Meta also requires pre-approved
    message templates rather than free-form text; this integration sends free-form messages, so it
    works best for time-sensitive notifications sent shortly after a client last messaged you.
  </p>
  <div class="form-group">
    <label>Phone number ID</label>
    <input type="text" name="whatsapp_phone_number_id" value="<?= View::e($settings['whatsapp_phone_number_id'] ?? '') ?>" placeholder="e.g. 109876543210123">
  </div>
  <div class="form-group">
    <label>Access token</label>
    <input type="password" name="whatsapp_access_token" placeholder="<?= !empty($settings['whatsapp_access_token']) ? '••••••••••••••••  (leave blank to keep current)' : 'EAAG...' ?>">
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
</form>
