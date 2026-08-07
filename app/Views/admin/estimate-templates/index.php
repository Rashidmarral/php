<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.templates.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.templates.hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.templates.add') ?></h3>
  <form method="post" action="/admin/estimate-templates" class="form-row" style="grid-template-columns:60px 1fr 1fr 1fr 90px auto;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('admin.templates.icon') ?></label><input type="text" name="icon" value="🏗️" maxlength="10"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_en') ?></label><input type="text" name="name_en" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" required dir="rtl"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.building_type') ?></label><input type="text" name="building_type" placeholder="e.g. villa"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<table class="data">
  <thead><tr><th></th><th><?= t('admin.templates.name_en_col') ?></th><th><?= t('admin.templates.name_ar_col') ?></th><th><?= t('common.building_type') ?></th><th><?= t('admin.templates.items_col') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th><?= t('common.default') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($templates as $t): $fid = 'tpl-' . $t['id']; $itemWord = (int) $t['item_count'] === 1 ? t('admin.templates.item_singular') : t('admin.templates.item_plural'); ?>
    <form id="<?= $fid ?>" method="post" action="/admin/estimate-templates/<?= $t['id'] ?>"><?= Csrf::field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="icon" value="<?= View::e($t['icon']) ?>" maxlength="10" style="width:48px;text-align:center;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= View::e($t['name_en']) ?>" style="min-width:150px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= View::e($t['name_ar']) ?>" dir="rtl" style="min-width:150px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="building_type" value="<?= View::e($t['building_type'] ?? '') ?>" style="width:110px;"></td>
      <td><a href="/admin/estimate-templates/<?= $t['id'] ?>/items"><?= (int) $t['item_count'] ?> <?= $itemWord ?> →</a></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$t['sort_order']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $t['is_active'] ? 'checked' : '' ?>></td>
      <td>
        <?php if ($t['is_default_choice']): ?>
          <span class="badge badge-blue"><?= t('admin.templates.default') ?></span>
        <?php else: ?>
          <form method="post" action="/admin/estimate-templates/<?= $t['id'] ?>/default" style="display:inline;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('admin.templates.make_default') ?></button>
          </form>
        <?php endif; ?>
      </td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/estimate-templates/<?= $t['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.templates.delete_confirm') ?>');" style="display:inline;">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($templates)): ?>
    <tr><td colspan="9" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.templates.none_yet') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
