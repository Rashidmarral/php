<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Quick Estimate Data</h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions">Regions</a>
  <a href="/admin/quick-estimate/foundations">Foundation Types</a>
  <a href="/admin/quick-estimate/addons" class="active">Add-ons</a>
  <a href="/admin/quick-estimate/leads">Leads</a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Add an add-on</h3>
  <form method="post" action="/admin/quick-estimate/addons">
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
      <div class="form-group"><label>Unit price (SAR)</label><input type="number" step="0.01" name="unit_price" value="0"></div>
      <div class="form-group">
        <label>Unit type</label>
        <select name="unit_type">
          <option value="sqm">Per m² (sqm)</option>
          <option value="ton">Per ton</option>
          <option value="unit">Per unit</option>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
      <div class="form-group"><label><input type="checkbox" name="is_pro" value="1" style="width:auto;display:inline-block;"> Mark as PRO add-on</label></div>
    </div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
</div>

<table class="data">
  <thead><tr><th>Name (EN)</th><th>Name (AR)</th><th>Unit price</th><th>Unit</th><th>PRO</th><th>Order</th><th>Active</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($addons as $a): $fid = 'addon-' . $a['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/quick-estimate/addons/<?= $a['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= View::e($a['name_en']) ?>" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= View::e($a['name_ar']) ?>" dir="rtl" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="unit_price" value="<?= View::e((string)$a['unit_price']) ?>" style="width:90px;"></td>
      <td>
        <select form="<?= $fid ?>" name="unit_type" style="width:90px;">
          <?php foreach (['sqm'=>'m²','ton'=>'ton','unit'=>'unit'] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= $a['unit_type']===$val?'selected':'' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_pro" value="1" <?= $a['is_pro'] ? 'checked' : '' ?>></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$a['sort_order']) ?>" style="width:65px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $a['is_active'] ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
        <form method="post" action="/admin/quick-estimate/addons/<?= $a['id'] ?>/delete" onsubmit="return confirm('Delete this add-on?');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
