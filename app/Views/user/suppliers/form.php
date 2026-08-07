<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $supplier ? 'Edit Supplier' : 'New Supplier' ?></h1>
  <a href="/app/suppliers" class="btn btn-light">← Back to suppliers</a>
</div>

<form method="post" action="<?= $supplier ? '/app/suppliers/' . $supplier['id'] : '/app/suppliers' ?>" class="card" style="max-width:640px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Supplier name (English)</label><input type="text" name="name" required value="<?= View::e($supplier['name'] ?? '') ?>"></div>
    <div class="form-group"><label>Supplier name (Arabic)</label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($supplier['name_ar'] ?? '') ?>" placeholder="اسم المورد"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Category</label><input type="text" name="category" placeholder="e.g. Steel, Electrical, Concrete" value="<?= View::e($supplier['category'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Contact person</label><input type="text" name="contact_name" value="<?= View::e($supplier['contact_name'] ?? '') ?>"></div>
    <div class="form-group"><label>Phone</label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= View::e($supplier['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($supplier['email'] ?? '') ?>"></div>
  <div class="form-group"><label>Address</label><input type="text" name="address" value="<?= View::e($supplier['address'] ?? '') ?>"></div>
  <div class="form-group"><label>Notes</label><textarea name="notes"><?= View::e($supplier['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $supplier ? 'Save changes' : 'Add supplier' ?></button>
</form>
