<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $material ? 'Edit Material' : 'New Material' ?></h1>
  <a href="/app/materials" class="btn btn-light">← Back to materials</a>
</div>

<form method="post" action="<?= $material ? '/app/materials/' . $material['id'] : '/app/materials' ?>" class="card" style="max-width:640px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Name</label><input type="text" name="name" required value="<?= View::e($material['name'] ?? '') ?>"></div>
    <div class="form-group"><label>SKU</label><input type="text" name="sku" value="<?= View::e($material['sku'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Category</label><input type="text" name="category" placeholder="e.g. Concrete, Electrical" value="<?= View::e($material['category'] ?? '') ?>"></div>
    <div class="form-group"><label>Supplier</label>
      <select name="supplier_id">
        <option value="">— None —</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (($material['supplier_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= View::e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Unit</label><input type="text" name="unit" value="<?= View::e($material['unit'] ?? 'unit') ?>" placeholder="e.g. m², ton, bag, unit"></div>
    <div class="form-group"><label>Unit cost (SAR)</label><input type="number" step="0.01" name="unit_cost" value="<?= View::e((string)($material['unit_cost'] ?? 0)) ?>"></div>
  </div>
  <div class="form-group"><label>Notes</label><textarea name="notes"><?= View::e($material['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $material ? 'Save changes' : 'Add material' ?></button>
</form>
