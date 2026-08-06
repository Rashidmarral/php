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
  <div class="form-group"><label>Unit</label><input type="text" name="unit" value="<?= View::e($material['unit'] ?? 'unit') ?>" placeholder="e.g. m², ton, bag, unit"></div>

  <div class="form-row">
    <div class="form-group"><label>Material cost (SAR)</label><input type="number" step="0.01" id="material-cost" name="material_cost" value="<?= View::e((string)($material['material_cost'] ?? 0)) ?>"></div>
    <div class="form-group"><label>Labor cost (SAR)</label><input type="number" step="0.01" id="labor-cost" name="labor_cost" value="<?= View::e((string)($material['labor_cost'] ?? 0)) ?>"></div>
  </div>
  <p class="help-text">Combined rate: <strong id="combined-rate"><?= number_format((float)($material['material_cost'] ?? 0) + (float)($material['labor_cost'] ?? 0), 2) ?></strong> SAR per unit</p>

  <div class="form-group"><label>Notes</label><textarea name="notes"><?= View::e($material['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $material ? 'Save changes' : 'Add material' ?></button>
</form>

<script>
(function() {
  const m = document.getElementById('material-cost');
  const l = document.getElementById('labor-cost');
  const out = document.getElementById('combined-rate');
  function recalc() { out.textContent = ((parseFloat(m.value) || 0) + (parseFloat(l.value) || 0)).toFixed(2); }
  m.addEventListener('input', recalc);
  l.addEventListener('input', recalc);
})();
</script>
