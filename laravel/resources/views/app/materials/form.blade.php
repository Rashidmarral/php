@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= $material ? t('user.materials.edit_title') : t('user.materials.new_title') ?></h1>
  <a href="/app/materials" class="btn btn-light"><?= t('user.materials.back_to_materials') ?></a>
</div>

<form method="post" action="<?= $material ? '/app/materials/' . $material['id'] : '/app/materials' ?>" class="card" style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required value="<?= e($material['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($material['name_ar'] ?? '') ?>" placeholder="الاسم بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.materials.sku') ?></label><input type="text" name="sku" value="<?= e($material['sku'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.category') ?></label><input type="text" name="category" placeholder="e.g. Concrete, Electrical" value="<?= e($material['category'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.supplier') ?></label>
      <select name="supplier_id">
        <option value=""><?= t('common.none') ?></option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= (($material['supplier_id'] ?? null) == $s['id']) ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <label><?= t('common.unit') ?></label>
    <input type="text" name="unit" list="uom-options" value="<?= e($material['unit'] ?? 'unit') ?>" placeholder="e.g. m², ton, bag, unit">
    <datalist id="uom-options">
      <?php foreach ($units as $u): ?><option value="<?= e($u['code']) ?>"><?= e($u['name']) ?></option><?php endforeach; ?>
    </datalist>
  </div>

  <div class="form-row">
    <div class="form-group"><label><?= t('user.materials.material_cost') ?></label><input type="number" step="0.01" id="material-cost" name="material_cost" value="<?= e((string)($material['material_cost'] ?? 0)) ?>"></div>
    <div class="form-group"><label><?= t('user.materials.labor_cost') ?></label><input type="number" step="0.01" id="labor-cost" name="labor_cost" value="<?= e((string)($material['labor_cost'] ?? 0)) ?>"></div>
  </div>
  <p class="help-text"><?= t('user.materials.combined_rate') ?> <strong id="combined-rate"><?= number_format((float)($material['material_cost'] ?? 0) + (float)($material['labor_cost'] ?? 0), 2) ?></strong> SAR per unit</p>

  <div class="form-group"><label><?= t('common.notes') ?></label><textarea name="notes"><?= e($material['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $material ? t('common.save_changes') : t('user.materials.add_material') ?></button>
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

@endsection
