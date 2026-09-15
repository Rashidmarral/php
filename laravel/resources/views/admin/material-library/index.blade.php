@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.material_library.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.material_library.hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.material_library.add') ?></h3>
  <form method="post" action="/admin/material-library">
    <?= csrf_field() ?>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="form-group"><label><?= t('user.materials.sku') ?></label><input type="text" name="sku"></div>
      <div class="form-group"><label><?= t('common.name') ?> (EN)</label><input type="text" name="name" required></div>
      <div class="form-group"><label><?= t('common.name') ?> (AR)</label><input type="text" name="name_ar" dir="rtl"></div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
      <div class="form-group">
        <label><?= t('common.category') ?></label>
        <select name="category">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('common.unit') ?></label><input type="text" name="unit" value="each"></div>
      <div class="form-group"><label><?= t('user.materials.material_cost') ?></label><input type="number" step="0.01" name="material_cost" value="0"></div>
      <div class="form-group"><label><?= t('user.materials.labor_cost') ?></label><input type="number" step="0.01" name="labor_cost" value="0"></div>
      <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    </div>
    <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
  <input type="text" id="lib-search" placeholder="<?= t('admin.material_library.search_placeholder') ?>" style="flex:1;min-width:220px;">
  <select id="lib-category-filter">
    <option value=""><?= t('user.materials.all_categories') ?> (<?= count($items) ?>)</option>
    <?php foreach ($categories as $cat): ?>
      <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div style="overflow-x:auto;">
<table class="data" id="lib-table">
  <thead><tr><th><?= t('user.materials.sku') ?></th><th><?= t('common.name') ?></th><th><?= t('common.category') ?></th><th><?= t('common.unit') ?></th><th><?= t('user.materials.material_cost') ?></th><th><?= t('user.materials.labor_cost') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($items as $it): $fid = 'lib-' . $it->id; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/material-library/<?= $it->id ?>"><?= csrf_field() ?></form>
    <tr data-category="<?= e(strtolower($it->category)) ?>" data-search="<?= e(strtolower($it->name . ' ' . $it->sku)) ?>">
      <td><input form="<?= $fid ?>" type="text" name="sku" value="<?= e($it->sku ?? '') ?>" style="width:100px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name" value="<?= e($it->name) ?>" style="min-width:180px;"></td>
      <td>
        <select form="<?= $fid ?>" name="category" style="min-width:140px;">
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat) ?>" <?= $it->category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input form="<?= $fid ?>" type="text" name="unit" value="<?= e($it->unit) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="material_cost" value="<?= e((string)$it->material_cost) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="labor_cost" value="<?= e((string)$it->labor_cost) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= e((string)$it->sort_order) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $it->is_active ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;white-space:nowrap;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/material-library/<?= $it->id ?>/delete" onsubmit="return confirm('<?= t('admin.material_library.delete_confirm') ?>');" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($items)): ?>
    <tr><td colspan="9" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.material_library.none_yet') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

<script>
(function() {
  const search = document.getElementById('lib-search');
  const categoryFilter = document.getElementById('lib-category-filter');
  const rows = document.querySelectorAll('#lib-table tbody tr[data-search]');
  function apply() {
    const q = search.value.toLowerCase();
    const cat = categoryFilter.value;
    rows.forEach(tr => {
      const matchesSearch = tr.dataset.search.includes(q);
      const matchesCategory = !cat || tr.dataset.category === cat;
      tr.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
    });
  }
  search.addEventListener('input', apply);
  categoryFilter.addEventListener('change', apply);
})();
</script>

@endsection
