<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Quick Estimate Data</h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions">Regions</a>
  <a href="/admin/quick-estimate/foundations" class="active">Foundation Types</a>
  <a href="/admin/quick-estimate/addons">Add-ons</a>
  <a href="/admin/quick-estimate/leads">Leads</a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Add a foundation type</h3>
  <form method="post" action="/admin/quick-estimate/foundations">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group"><label>Name (English)</label><input type="text" name="name_en" required></div>
      <div class="form-group"><label>Name (Arabic)</label><input type="text" name="name_ar" required dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Description (English)</label><input type="text" name="description_en"></div>
      <div class="form-group"><label>Description (Arabic)</label><input type="text" name="description_ar" dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Price / m²</label><input type="number" step="0.01" name="price_per_sqm" value="0"></div>
      <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
</div>

<table class="data">
  <thead><tr><th>Name (EN)</th><th>Name (AR)</th><th>Price/m²</th><th>Order</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($foundations as $f): $fid = 'foundation-' . $f['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/quick-estimate/foundations/<?= $f['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= View::e($f['name_en']) ?>" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= View::e($f['name_ar']) ?>" dir="rtl" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="price_per_sqm" value="<?= View::e((string)$f['price_per_sqm']) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$f['sort_order']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $f['is_active'] ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
        <form method="post" action="/admin/quick-estimate/foundations/<?= $f['id'] ?>/delete" onsubmit="return confirm('Delete this foundation type?');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
