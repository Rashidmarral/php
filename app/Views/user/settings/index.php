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
  <div class="form-row">
    <div class="form-group"><label>Company name (English)</label><input type="text" name="name" value="<?= View::e($company['name']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label>Company name (Arabic)</label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($company['name_ar'] ?? '') ?>" placeholder="اسم الشركة" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= View::e($company['phone']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label>City</label><input type="text" name="city" value="<?= View::e($company['city']) ?>" <?= $ro ?>></div>
  </div>
  <div class="form-group"><label>Address (free text, shown on documents)</label><input type="text" name="address" value="<?= View::e($company['address'] ?? '') ?>" placeholder="Street, district" <?= $ro ?>></div>
  <div class="form-row">
    <div class="form-group"><label>CR number</label><input type="text" name="cr_number" value="<?= View::e($company['cr_number']) ?>" <?= $ro ?>></div>
    <div class="form-group"><label>VAT number</label><input type="text" name="vat_number" value="<?= View::e($company['vat_number']) ?>" <?= $ro ?>></div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;">ZATCA-compliant address</h3>
  <p class="help-text" style="margin-top:-8px;">Used on the structured invoice data reported to ZATCA — building number and postal code are 4/5-digit National Address fields (see your building's address plate or the Saudi Post National Address service).</p>
  <div class="form-row">
    <div class="form-group"><label>Building number</label><input type="text" name="building_number" maxlength="4" value="<?= View::e($company['building_number'] ?? '') ?>" placeholder="1234" <?= $ro ?>></div>
    <div class="form-group"><label>Street name</label><input type="text" name="street_name" value="<?= View::e($company['street_name'] ?? '') ?>" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>District</label><input type="text" name="district" value="<?= View::e($company['district'] ?? '') ?>" <?= $ro ?>></div>
    <div class="form-group"><label>Postal code</label><input type="text" name="postal_code" maxlength="5" value="<?= View::e($company['postal_code'] ?? '') ?>" placeholder="12345" <?= $ro ?>></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Additional number</label><input type="text" name="additional_number" maxlength="4" value="<?= View::e($company['additional_number'] ?? '') ?>" placeholder="6789" <?= $ro ?>></div>
    <div class="form-group"><label>Country</label><input type="text" value="Saudi Arabia" disabled></div>
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
