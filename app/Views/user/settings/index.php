<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; $ro = Auth::isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1>Settings</h1>
</div>

<form method="post" action="/app/settings" enctype="multipart/form-data" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>

  <h3 style="font-size:14px;">Company profile</h3>
  <div class="form-group">
    <label>Company logo</label>
    <?php if (!empty($company['logo_path'])): ?>
      <div style="margin-bottom:8px;"><img src="<?= View::e($company['logo_path']) ?>" alt="Logo" style="height:56px;border-radius:8px;border:1px solid var(--border);"></div>
    <?php endif; ?>
    <?php if (Auth::isCompanyOwner()): ?><input type="file" name="logo" accept="image/png,image/jpeg,image/webp"><?php endif; ?>
  </div>
  <div class="form-group"><label>Company name</label><input type="text" name="name" value="<?= View::e($company['name']) ?>" <?= $ro ?>></div>
  <div class="form-row">
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= View::e($company['phone']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label>City</label><input type="text" name="city" value="<?= View::e($company['city']) ?>" <?= $ro ?>></div>
  </div>
  <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= View::e($company['address'] ?? '') ?>" placeholder="Street, district" <?= $ro ?>></div>
  <div class="form-row">
    <div class="form-group"><label>CR number</label><input type="text" name="cr_number" value="<?= View::e($company['cr_number']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label>VAT number</label><input type="text" name="vat_number" value="<?= View::e($company['vat_number']) ?>" <?= $ro ?>></div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;">Business controls</h3>
  <div class="form-row">
    <div class="form-group">
      <label>Default markup (%)</label>
      <input type="number" step="0.01" name="default_markup_percent" value="<?= View::e((string)($company['default_markup_percent'] ?? 0)) ?>" <?= $ro ?>>
      <p class="help-text">Applied as a suggested default when pricing new estimates.</p>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="client_portal_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($company['client_portal_enabled']) ? 'checked' : '' ?> <?= $ro ?>> Enable client portal</label>
      <p class="help-text">Lets clients you invite log in to view their own projects, estimates, and invoices.</p>
    </div>
  </div>

  <?php if (Auth::isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary">Save changes</button>
  <?php else: ?>
    <p class="help-text">Only the company owner can edit these settings.</p>
  <?php endif; ?>
</form>
