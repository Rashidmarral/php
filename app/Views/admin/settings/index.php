<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Platform Settings</h1>
</div>

<div class="tabs">
  <a href="/admin/settings" class="active">General</a>
  <a href="/admin/settings/payments">Payment Methods</a>
  <a href="/admin/settings/legal">Legal & Branding</a>
  <a href="/admin/settings/header">Header & Footer</a>
  <a href="/admin/settings/notifications">Notifications</a>
  <a href="/admin/settings/email">Email</a>
</div>

<form method="post" action="/admin/settings" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group">
      <label>Free trial length (days)</label>
      <input type="number" name="trial_days" min="0" value="<?= View::e($settings['trial_days'] ?? '14') ?>">
      <p class="help-text">How many days a new company gets free before their trial ends. Set to 0 to disable trials.</p>
    </div>
    <div class="form-group">
      <label>VAT rate (%)</label>
      <input type="number" step="0.01" name="vat_rate" value="<?= View::e($settings['vat_rate'] ?? '15') ?>">
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Site name</label><input type="text" name="site_name" value="<?= View::e($settings['site_name'] ?? 'BuildXact Saudi') ?>"></div>
    <div class="form-group"><label>Currency</label><input type="text" name="currency" value="<?= View::e($settings['currency'] ?? 'SAR') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Support email</label><input type="email" name="support_email" value="<?= View::e($settings['support_email'] ?? '') ?>"></div>
    <div class="form-group"><label>Support phone</label><input type="tel" name="support_phone" value="<?= View::e($settings['support_phone'] ?? '') ?>"></div>
  </div>
  <button type="submit" class="btn btn-primary">Save settings</button>
</form>

<div class="card" style="max-width:680px;margin-top:20px;">
  <h3>What this controls</h3>
  <p class="help-text">
    Trial length applies to every new company that signs up from the pricing page from now on
    (existing trials already in progress keep their original end date). VAT rate is used on the
    Quick Estimate calculator and can be reflected on new invoices going forward.
  </p>
</div>
