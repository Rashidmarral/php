<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Estimate Template Library</h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">Templates power the "Start from a template" gallery on the estimate creation screen. Companies on plans without the Estimate Template Library feature won't see this gallery — control that in <a href="/admin/plans">Plans</a>.</p>

<div class="card" style="margin-bottom:24px;">
  <h3>Add a template</h3>
  <form method="post" action="/admin/estimate-templates" class="form-row" style="grid-template-columns:60px 1fr 1fr 1fr 90px auto;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Icon</label><input type="text" name="icon" value="🏗️" maxlength="10"></div>
    <div class="form-group" style="margin:0;"><label>Name (English)</label><input type="text" name="name_en" required></div>
    <div class="form-group" style="margin:0;"><label>Name (Arabic)</label><input type="text" name="name_ar" required dir="rtl"></div>
    <div class="form-group" style="margin:0;"><label>Building type</label><input type="text" name="building_type" placeholder="e.g. villa"></div>
    <div class="form-group" style="margin:0;"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
</div>

<table class="data">
  <thead><tr><th></th><th>Name (EN)</th><th>Name (AR)</th><th>Building type</th><th>Items</th><th>Order</th><th>Active</th><th>Default</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($templates as $t): $fid = 'tpl-' . $t['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/estimate-templates/<?= $t['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="icon" value="<?= View::e($t['icon']) ?>" maxlength="10" style="width:48px;text-align:center;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= View::e($t['name_en']) ?>" style="min-width:150px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= View::e($t['name_ar']) ?>" dir="rtl" style="min-width:150px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="building_type" value="<?= View::e($t['building_type'] ?? '') ?>" style="width:110px;"></td>
      <td><a href="/admin/estimate-templates/<?= $t['id'] ?>/items"><?= (int) $t['item_count'] ?> item<?= (int) $t['item_count'] === 1 ? '' : 's' ?> →</a></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$t['sort_order']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>></td>
      <td>
        <?php if ($t['is_default_choice']): ?>
          <span class="badge badge-blue">Default</span>
        <?php else: ?>
          <form method="post" action="/admin/estimate-templates/<?= $t['id'] ?>/default" style="display:inline;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light">Make default</button>
          </form>
        <?php endif; ?>
      </td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
        <form method="post" action="/admin/estimate-templates/<?= $t['id'] ?>/delete" onsubmit="return confirm('Delete this template and all its line items?');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger">Delete</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($templates)): ?>
    <tr><td colspan="9" class="help-text" style="text-align:center;padding:20px;">No templates yet — add one above.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
