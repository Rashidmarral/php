@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.qe.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions"><?= t('admin.qe.tab_regions') ?></a>
  <a href="/admin/quick-estimate/foundations"><?= t('admin.qe.tab_foundations') ?></a>
  <a href="/admin/quick-estimate/addons" class="active"><?= t('admin.qe.tab_addons') ?></a>
  <a href="/admin/quick-estimate/leads"><?= t('admin.qe.tab_leads') ?></a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.qe.add_addon') ?></h3>
  <form method="post" action="/admin/quick-estimate/addons">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name_en" required></div>
      <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" required dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.description_en') ?></label><input type="text" name="description_en"></div>
      <div class="form-group"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.qe.unit_price') ?></label><input type="number" step="0.01" name="unit_price" value="0"></div>
      <div class="form-group">
        <label><?= t('admin.qe.unit_type') ?></label>
        <input type="text" name="unit_type" value="sqm" placeholder="sqm, m³, ton, unit, m...">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.qe.qty_mode') ?></label>
        <select name="qty_mode">
          <option value="area"><?= t('admin.qe.qty_mode_area') ?></option>
          <option value="manual"><?= t('admin.qe.qty_mode_manual') ?></option>
        </select>
      </div>
      <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="is_pro" value="1" style="width:auto;display:inline-block;"> <?= t('admin.qe.mark_pro') ?></label></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<p class="help-text" style="margin-bottom:10px;"><?= t('admin.qe.qty_mode_hint') ?></p>

<table class="data">
  <thead><tr><th><?= t('admin.templates.name_en_col') ?></th><th><?= t('admin.templates.name_ar_col') ?></th><th><?= t('admin.qe.unit_price') ?></th><th><?= t('common.unit') ?></th><th><?= t('admin.qe.pricing_mode') ?></th><th><?= t('admin.qe.pro') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($addons as $a): $fid = 'addon-' . $a['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/quick-estimate/addons/<?= $a['id'] ?>"><?= csrf_field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= e($a['name_en']) ?>" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= e($a['name_ar']) ?>" dir="rtl" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="unit_price" value="<?= e((string)$a['unit_price']) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="unit_type" value="<?= e($a['unit_type']) ?>" style="width:80px;"></td>
      <td>
        <select form="<?= $fid ?>" name="qty_mode" style="width:120px;">
          <option value="area" <?= ($a['qty_mode'] ?? 'area')==='area'?'selected':'' ?>><?= t('admin.qe.qty_mode_area') ?></option>
          <option value="manual" <?= ($a['qty_mode'] ?? 'area')==='manual'?'selected':'' ?>><?= t('admin.qe.qty_mode_manual') ?></option>
        </select>
      </td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_pro" value="1" <?= $a['is_pro'] ? 'checked' : '' ?>></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= e((string)$a['sort_order']) ?>" style="width:65px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $a['is_active'] ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/quick-estimate/addons/<?= $a['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.qe.delete_addon_confirm') ?>');" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

@endsection
