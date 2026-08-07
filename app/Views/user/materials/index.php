<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Materials & Pricing Library</h1>
  <a href="/app/materials/create" class="btn btn-primary">+ New Material</a>
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
  <div class="kpi"><div class="label">Total Items</div><div class="value"><?= count($materials) ?></div></div>
  <div class="kpi"><div class="label">Categories</div><div class="value"><?= count($categories) ?></div></div>
  <div class="kpi"><div class="label">Avg Combined Rate</div><div class="value"><?= View::money($avgRate) ?></div></div>
  <div class="kpi"><div class="label">Library Value</div><div class="value"><?= View::money($totalValue) ?></div></div>
</div>

<div class="grid grid-2" style="margin-bottom:20px;">
  <div class="card">
    <h3 style="font-size:14px;">Import from CSV</h3>
    <p class="help-text">Columns: sku, name, category, unit, material_price, labor_price, supplier (sku/name match existing rows; a single unit_cost column also works if you don't split labor).</p>
    <form method="post" action="/app/materials/import" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;">
      <?= Csrf::field() ?>
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <button type="submit" class="btn btn-outline btn-sm">Import</button>
    </form>
  </div>
  <div class="card">
    <h3 style="font-size:14px;">Sync from Google Sheets</h3>
    <?php if (!empty($company['price_sync_url'])): ?>
      <p class="help-text">Linked sheet is configured. <?= !empty($company['price_sync_last_at']) ? 'Last synced: ' . View::e($company['price_sync_last_at']) : 'Never synced yet.' ?></p>
      <form method="post" action="/app/materials/sync-sheet">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-outline btn-sm">🔄 Sync now</button>
      </form>
    <?php else: ?>
      <p class="help-text">No Google Sheet linked yet. Set one up on the <a href="/app/integrations">Integrations</a> page.</p>
    <?php endif; ?>
  </div>
</div>

<?php if (empty($materials)): ?>
  <div class="card empty-state">
    <div class="icon">📦</div>
    <h3>No materials yet</h3>
    <p>Build a reusable pricing library so your team estimates consistently.</p>
    <a href="/app/materials/create" class="btn btn-primary">+ New Material</a>
  </div>
<?php else: ?>
  <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
    <input type="text" id="materials-search" placeholder="Search items or suppliers..." style="flex:1;min-width:220px;">
    <select id="materials-category-filter">
      <option value="">All categories (<?= count($materials) ?>)</option>
      <?php foreach ($categories as $cat => $count): ?>
        <option value="<?= View::e(strtolower($cat)) ?>"><?= View::e($cat) ?> (<?= $count ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>

  <table class="data" id="materials-table">
    <thead><tr><th>Description</th><th>Category</th><th>Unit</th><th>Material</th><th>Labor</th><th>Rate</th><th>Supplier</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($materials as $m): ?>
      <tr data-category="<?= View::e(strtolower($m['category'] ?: 'other')) ?>" data-search="<?= View::e(strtolower($m['name'] . ' ' . ($m['supplier_name'] ?? ''))) ?>">
        <td><?= View::e(View::local($m, 'name')) ?><?php if ($m['sku']): ?><br><span class="help-text"><?= View::e($m['sku']) ?></span><?php endif; ?></td>
        <td><?php if ($m['category']): ?><span class="badge badge-gray"><?= View::e($m['category']) ?></span><?php endif; ?></td>
        <td><?= View::e($m['unit']) ?></td>
        <td><?= View::money((float)($m['material_cost'] ?? 0)) ?></td>
        <td><?= View::money((float)($m['labor_cost'] ?? 0)) ?></td>
        <td><strong><?= View::money((float)$m['unit_cost']) ?></strong>/<?= View::e($m['unit']) ?></td>
        <td><?= View::e($m['supplier_name'] ? View::local($m, 'supplier_name') : '—') ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/materials/<?= $m['id'] ?>/edit" class="btn btn-sm btn-light">Edit</a>
          <form method="post" action="/app/materials/<?= $m['id'] ?>/delete" onsubmit="return confirm('Remove this material?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
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
