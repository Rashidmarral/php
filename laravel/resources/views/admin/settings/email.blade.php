@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'email'])

<form method="post" action="/admin/settings/email" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">✉️ <?= t('admin.settings.smtp_email') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="smtp_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['smtp_enabled']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text">
    Used for team-member invite emails (and future transactional email — invoice delivery, password
    reset). Works with any real SMTP mailbox or transactional provider: your own company email
    (Microsoft 365, Google Workspace), or a dedicated sender like
    <a href="https://sendgrid.com" target="_blank" rel="noopener">SendGrid</a>,
    <a href="https://www.mailgun.com" target="_blank" rel="noopener">Mailgun</a>, or
    <a href="https://www.brevo.com" target="_blank" rel="noopener">Brevo</a> — all publish SMTP
    host/port/username/password in their dashboard under "SMTP settings" or "SMTP relay".
  </p>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.smtp_host') ?></label><input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="smtp.example.com"></div>
    <div class="form-group"><label><?= t('admin.settings.port') ?></label><input type="number" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>"></div>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.encryption') ?></label>
    <select name="smtp_encryption">
      <option value="tls" <?= ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (port 587, most common)</option>
      <option value="ssl" <?= ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL/TLS (port 465)</option>
      <option value="none" <?= ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None (not recommended)</option>
    </select>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.username') ?></label><input type="text" name="smtp_username" value="<?= e($settings['smtp_username'] ?? '') ?>"></div>
    <div class="form-group">
      <label><?= t('common.password') ?></label>
      <div class="password-field">
        <input type="password" name="smtp_password" placeholder="<?= !empty($settings['smtp_password']) ? '••••••••••••••••  (leave blank to keep current)' : 'App password or SMTP key' ?>">
        <?= passwordToggle() ?>
      </div>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.from_email') ?></label><input type="email" name="smtp_from_email" value="<?= e($settings['smtp_from_email'] ?? '') ?>" placeholder="no-reply@yourdomain.com"></div>
    <div class="form-group"><label><?= t('admin.settings.from_name') ?></label><input type="text" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? 'BuildXact Saudi') ?>"></div>
  </div>
  <p class="help-text">For Google Workspace/Gmail: use <code>smtp.gmail.com</code>, port 587, and a 16-character <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener">App Password</a> (not your regular password — Gmail requires 2FA enabled to generate one).</p>

  <button type="submit" class="btn btn-primary"><?= t('common.save') ?></button>
</form>

@endsection
