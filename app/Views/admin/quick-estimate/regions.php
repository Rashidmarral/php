<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.qe.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions" class="active"><?= t('admin.qe.tab_regions') ?></a>
  <a href="/admin/quick-estimate/foundations"><?= t('admin.qe.tab_foundations') ?></a>
  <a href="/admin/quick-estimate/addons"><?= t('admin.qe.tab_addons') ?></a>
  <a href="/admin/quick-estimate/leads"><?= t('admin.qe.tab_leads') ?></a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.qe.add_region') ?></h3>
  <form method="post" action="/admin/quick-estimate/regions" class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr auto;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_en') ?></label><input type="text" name="name_en" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" required dir="rtl"></div>
    <div class="form-group" style="margin:0;"><label><?= t('admin.qe.price_per_sqm') ?></label><input type="number" step="0.01" name="price_per_sqm" value="0"></div>
    <div class="form-group" style="margin:0;"><label><?= t('admin.qe.multiplier') ?></label><input type="number" step="0.01" name="multiplier" value="1"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<table class="data">
  <thead><tr><th><?= t('admin.templates.name_en_col') ?></th><th><?= t('admin.templates.name_ar_col') ?></th><th><?= t('admin.qe.price_per_sqm') ?></th><th><?= t('admin.qe.multiplier') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($regions as $r): $fid = 'region-' . $r['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/quick-estimate/regions/<?= $r['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= View::e($r['name_en']) ?>" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= View::e($r['name_ar']) ?>" dir="rtl" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="price_per_sqm" value="<?= View::e((string)$r['price_per_sqm']) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="multiplier" value="<?= View::e((string)$r['multiplier']) ?>" style="width:80px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$r['sort_order']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $r['is_active'] ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/quick-estimate/regions/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.qe.delete_region_confirm') ?>');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
