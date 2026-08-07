<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= View::e($template['icon']) ?> <?= View::e($template['name_en']) ?> — Line Items</h1>
  <a href="/admin/estimate-templates" class="btn btn-light">← Back to templates</a>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">These are the default line items a company gets when they create an estimate from this template. Total at default quantities: <strong><?= View::money($subtotal) ?></strong>.</p>

<div class="card" style="margin-bottom:24px;">
  <h3>Add a line item</h3>
  <form method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items">
    <?= Csrf::field() ?>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="form-group"><label>Section number</label><input type="text" name="section_number" value="1.0" placeholder="e.g. 2.1"></div>
      <div class="form-group"><label>Section title (English)</label><input type="text" name="section_title_en"></div>
      <div class="form-group"><label>Section title (Arabic)</label><input type="text" name="section_title_ar" dir="rtl"></div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="form-group"><label>Item number</label><input type="text" name="item_number" value="1.1"></div>
      <div class="form-group"><label>Description (English)</label><input type="text" name="description_en" required></div>
      <div class="form-group"><label>Description (Arabic)</label><input type="text" name="description_ar" dir="rtl"></div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
      <div class="form-group">
        <label>Type</label>
        <select name="item_type">
          <option value="material">Material</option>
          <option value="labor">Labor</option>
          <option value="equipment">Equipment</option>
          <option value="subcontract">Subcontract</option>
        </select>
      </div>
      <div class="form-group"><label>Default qty</label><input type="number" step="0.01" name="default_qty" value="0"></div>
      <div class="form-group"><label>Unit (UOM)</label><input type="text" name="uom" value="each"></div>
      <div class="form-group"><label>Unit cost (SAR)</label><input type="number" step="0.01" name="unit_cost" value="0"></div>
      <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    </div>
    <button type="submit" class="btn btn-primary">Add line item</button>
  </form>
</div>

<div style="overflow-x:auto;">
<table class="data">
  <thead><tr>
    <th>Sec #</th><th>Section title</th><th>Item #</th><th>Description</th><th>Type</th><th>Qty</th><th>UOM</th><th>Unit cost</th><th>Order</th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($items as $it): $fid = 'item-' . $it['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items/<?= $it['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="section_number" value="<?= View::e($it['section_number']) ?>" style="width:60px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="section_title_en" value="<?= View::e($it['section_title_en']) ?>" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="item_number" value="<?= View::e($it['item_number']) ?>" style="width:60px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="description_en" value="<?= View::e($it['description_en']) ?>" style="min-width:200px;"></td>
      <td>
        <select form="<?= $fid ?>" name="item_type" style="width:110px;">
          <?php foreach (['material'=>'Material','labor'=>'Labor','equipment'=>'Equipment','subcontract'=>'Subcontract'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $it['item_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="default_qty" value="<?= View::e((string)$it['default_qty']) ?>" style="width:80px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="uom" value="<?= View::e($it['uom']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="unit_cost" value="<?= View::e((string)$it['unit_cost']) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$it['sort_order']) ?>" style="width:70px;"></td>
      <td style="display:flex;gap:6px;white-space:nowrap;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
        <form method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items/<?= $it['id'] ?>/delete" onsubmit="return confirm('Delete this line item?');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($items)): ?>
    <tr><td colspan="10" class="help-text" style="text-align:center;padding:20px;">No line items yet — add one above.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>
