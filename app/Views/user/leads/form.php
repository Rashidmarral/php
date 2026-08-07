<?php use App\Core\View; use App\Core\Csrf; $isEdit = $lead !== null; ?>
<div class="page-head">
  <h1><?= $isEdit ? 'Edit Lead' : 'New Lead' ?></h1>
  <a href="/app/leads" class="btn btn-light">← Back to Leads</a>
</div>

<form method="post" action="<?= $isEdit ? '/app/leads/' . $lead['id'] : '/app/leads' ?>" class="card" style="max-width:640px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= View::e($lead['name'] ?? '') ?>" required></div>
    <div class="form-group"><label>Company name (English)</label><input type="text" name="company_name" value="<?= View::e($lead['company_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Company name (Arabic)</label><input type="text" name="company_name_ar" dir="rtl" value="<?= View::e($lead['company_name_ar'] ?? '') ?>" placeholder="اسم الشركة بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($lead['email'] ?? '') ?>"></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= View::e($lead['phone'] ?? '') ?>" placeholder="+966 5x xxx xxxx"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Source</label>
      <select name="source">
        <?php foreach ($sources as $s): ?>
          <option value="<?= $s ?>" <?= ($lead['source'] ?? '') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>Estimated value (SAR)</label><input type="number" step="0.01" name="estimated_value" value="<?= View::e((string)($lead['estimated_value'] ?? 0)) ?>"></div>
  </div>
  <?php if ($isEdit): ?>
    <div class="form-group">
      <label>Status</label>
      <select name="status">
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="form-group"><label>Notes</label><textarea name="notes" rows="4"><?= View::e($lead['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Add lead' ?></button>
</form>
