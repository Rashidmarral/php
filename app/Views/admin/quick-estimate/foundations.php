<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.qe.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions"><?= t('admin.qe.tab_regions') ?></a>
  <a href="/admin/quick-estimate/foundations" class="active"><?= t('admin.qe.tab_foundations') ?></a>
  <a href="/admin/quick-estimate/addons"><?= t('admin.qe.tab_addons') ?></a>
  <a href="/admin/quick-estimate/leads"><?= t('admin.qe.tab_leads') ?></a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.qe.add_foundation') ?></h3>
  <form method="post" action="/admin/quick-estimate/foundations">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name_en" required></div>
      <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" required dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.description_en') ?></label><input type="text" name="description_en"></div>
      <div class="form-group"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.qe.price_per_sqm') ?></label><input type="number" step="0.01" name="price_per_sqm" value="0"></div>
      <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<table class="data">
  <thead><tr><th><?= t('admin.templates.name_en_col') ?></th><th><?= t('admin.templates.name_ar_col') ?></th><th><?= t('admin.qe.price_per_sqm') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
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
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/quick-estimate/foundations/<?= $f['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.qe.delete_foundation_confirm') ?>');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
