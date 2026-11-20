<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/settings"><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/payments"><?= t('admin.settings.tab_payments') ?></a>
  <a href="/admin/settings/legal"><?= t('admin.settings.tab_legal') ?></a>
  <a href="/admin/settings/header"><?= t('admin.settings.tab_header') ?></a>
  <a href="/admin/settings/ai" class="active"><?= t('admin.settings.tab_ai') ?></a>
  <a href="/admin/settings/notifications"><?= t('admin.settings.tab_notifications') ?></a>
  <a href="/admin/settings/email"><?= t('admin.settings.tab_email') ?></a>
</div>

<div class="card" style="max-width:680px;margin-bottom:20px;">
  <h3 style="font-size:14px;">✨ "AI Estimate Generator" — works with zero setup</h3>
  <p class="help-text">
    Every company can already describe a project in plain language on the Create Estimate screen
    and get a draft estimate back — without any setup here — by matching the description against
    the built-in template library. Configuring a real API key below upgrades this to a genuine,
    tailored AI-written estimate instead of the closest matching template.
  </p>
</div>

<form method="post" action="/admin/settings/ai" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>
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
    <input type="text" name="ai_model" value="<?= View::e($settings['ai_model'] ?? 'claude-sonnet-5') ?>" placeholder="claude-sonnet-5">
    <p class="help-text"><?= t('admin.settings.model_hint') ?></p>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.api_key') ?></label>
    <div class="password-field">
      <input type="password" name="ai_api_key" placeholder="<?= !empty($settings['ai_api_key']) ? 'Saved — leave blank to keep it' : 'sk-ant-...' ?>">
      <?= View::passwordToggle() ?>
    </div>
  </div>
  <?php if (!empty($settings['ai_last_error'])): ?>
    <div class="alert alert-error"><?= t('admin.settings.last_api_error') ?> <?= View::e($settings['ai_last_error']) ?></div>
  <?php endif; ?>
  <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
</form>
