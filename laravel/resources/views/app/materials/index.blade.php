@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.materials.title') ?></h1>
  <div style="display:flex;gap:8px;">
    <a href="/app/materials/library" class="btn btn-outline"><?= t('user.materials.browse_library') ?></a>
    <a href="/app/materials/create" class="btn btn-primary"><?= t('user.materials.new') ?></a>
  </div>
</div>

<?php
$categories = [];
$totalValue = 0.0;
$totalRate = 0.0;
foreach ($materials as $m) {
    $cat = $m['category'] ?: 'Other';
    $categories[$cat] = ($categories[$cat] ?? 0) + 1;
    $totalValue += (float) $m['unit_cost'];
    $totalRate += (float) $m['unit_cost'];
}
$avgRate = count($materials) > 0 ? $totalRate / count($materials) : 0;
?>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.materials.total_items') ?></div><div class="value"><?= count($materials) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.materials.categories') ?></div><div class="value"><?= count($categories) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.materials.avg_combined_rate') ?></div><div class="value"><?= money($avgRate) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.materials.library_value') ?></div><div class="value"><?= money($totalValue) ?></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:20px;">
  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.materials.import_csv') ?></h3>
    <p class="help-text"><?= t('user.materials.import_csv_hint') ?></p>
    <form method="post" action="/app/materials/import" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;">
      <?= csrf_field() ?>
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <button type="submit" class="btn btn-outline btn-sm"><?= t('user.materials.import') ?></button>
    </form>
  </div>
  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.materials.sync_sheets') ?></h3>
    <?php if (!empty($company['price_sync_url'])): ?>
      <p class="help-text"><?= t('user.materials.sheet_linked') ?> <?= !empty($company['price_sync_last_at']) ? t('user.materials.last_synced') . ' ' . e($company['price_sync_last_at']) : t('user.materials.never_synced') ?></p>
      <form method="post" action="/app/materials/sync-sheet">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline btn-sm"><?= t('user.materials.sync_now') ?></button>
      </form>
    <?php else: ?>
      <p class="help-text"><?= t('user.materials.no_sheet_linked') ?> <a href="/app/integrations"><?= t('side.integrations') ?></a> page.</p>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($materials)): ?>
  <div class="card empty-state">
    <div class="icon">📦</div>
    <h3><?= t('user.materials.no_materials_title') ?></h3>
    <p><?= t('user.materials.no_materials_hint') ?></p>
    <a href="/app/materials/create" class="btn btn-primary"><?= t('user.materials.new') ?></a>
  </div>
<?php else: ?>
  <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    <input type="text" id="materials-search" placeholder="<?= t('user.materials.search_placeholder') ?>" style="flex:1;min-width:220px;">
    <select id="materials-category-filter">
      <option value=""><?= t('user.materials.all_categories') ?> (<?= count($materials) ?>)</option>
      <?php foreach ($categories as $cat => $count): ?>
        <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?> (<?= $count ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>

  <table class="data" id="materials-table">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.category') ?></th><th><?= t('common.unit') ?></th><th><?= t('user.materials.material_col') ?></th><th><?= t('user.materials.labor_col') ?></th><th><?= t('common.rate') ?></th><th><?= t('user.materials.supplier_col') ?></th><th><?= t('user.materials.stock_col') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($materials as $m):
      $reorderLevel = $m['reorder_level'] ?? null;
      $qtyOnHand = (float) ($m['qty_on_hand'] ?? 0);
      $lowStock = $reorderLevel !== null && $qtyOnHand <= (float) $reorderLevel;
    ?>
      <tr data-category="<?= e(strtolower($m['category'] ?: 'other')) ?>" data-search="<?= e(strtolower($m['name'] . ' ' . ($m['supplier_name'] ?? ''))) ?>">
        <td><?= e(local($m, 'name')) ?><?php if ($m['sku']): ?><br><span class="help-text"><?= e($m['sku']) ?></span><?php endif; ?></td>
        <td><?php if ($m['category']): ?><span class="badge badge-gray"><?= e($m['category']) ?></span><?php endif; ?></td>
        <td><?= e($m['unit']) ?></td>
        <td><?= money((float)($m['material_cost'] ?? 0)) ?></td>
        <td><?= money((float)($m['labor_cost'] ?? 0)) ?></td>
        <td><strong><?= money((float)$m['unit_cost']) ?></strong>/<?= e($m['unit']) ?></td>
        <td><?= e($m['supplier_name'] ? local($m, 'supplier_name') : '—') ?></td>
        <td>
          <a href="/app/materials/<?= $m['id'] ?>/stock"><?= number_format($qtyOnHand, 2) ?> <?= e($m['unit']) ?></a>
          <?php if ($lowStock): ?><br><span class="badge badge-red"><?= t('user.materials.low_stock') ?></span><?php endif; ?>
        </td>
        <td style="display:flex;gap:8px;">
          <a href="/app/materials/<?= $m['id'] ?>/stock" class="btn btn-sm btn-outline"><?= t('user.materials.manage_stock') ?></a>
          <a href="/app/materials/<?= $m['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <form method="post" action="/app/materials/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= t('user.materials.remove_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <script>
  (function() {
    const search = document.getElementById('materials-search');
    const categoryFilter = document.getElementById('materials-category-filter');
    const rows = document.querySelectorAll('#materials-table tbody tr');
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
<?php endif; ?>

@endsection
