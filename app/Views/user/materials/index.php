<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Materials & Pricing Library</h1>
  <a href="/app/materials/create" class="btn btn-primary">+ New Material</a>
</div>

<div class="grid grid-2" style="margin-bottom:20px;">
  <div class="card">
    <h3 style="font-size:14px;">Import from CSV</h3>
    <p class="help-text">Columns: sku, name, category, unit, unit_cost (sku and name used to match existing rows).</p>
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
  <table class="data">
    <thead><tr><th>SKU</th><th>Name</th><th>Category</th><th>Unit</th><th>Unit Cost</th><th>Supplier</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($materials as $m): ?>
      <tr>
        <td class="help-text"><?= View::e($m['sku']) ?></td>
        <td><?= View::e($m['name']) ?></td>
        <td><?php if ($m['category']): ?><span class="badge badge-gray"><?= View::e($m['category']) ?></span><?php endif; ?></td>
        <td><?= View::e($m['unit']) ?></td>
        <td><?= View::money((float)$m['unit_cost']) ?></td>
        <td><?= View::e($m['supplier_name'] ?? '—') ?></td>
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
<?php endif; ?>
