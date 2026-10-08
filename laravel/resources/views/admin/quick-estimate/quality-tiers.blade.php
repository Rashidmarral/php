@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.qe.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions"><?= t('admin.qe.tab_regions') ?></a>
  <a href="/admin/quick-estimate/foundations"><?= t('admin.qe.tab_foundations') ?></a>
  <a href="/admin/quick-estimate/addons"><?= t('admin.qe.tab_addons') ?></a>
  <a href="/admin/quick-estimate/quality-tiers" class="active"><?= t('admin.qe.tab_quality_tiers') ?></a>
  <a href="/admin/quick-estimate/leads"><?= t('admin.qe.tab_leads') ?></a>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.qe.add_quality_tier') ?></h3>
  <form method="post" action="/admin/quick-estimate/quality-tiers" class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr auto;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_en') ?></label><input type="text" name="name_en" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" required dir="rtl"></div>
    <div class="form-group" style="margin:0;"><label><?= t('admin.qe.multiplier') ?></label><input type="number" step="0.01" name="multiplier" value="1"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>

<table class="data">
  <thead><tr><th><?= t('admin.templates.name_en_col') ?></th><th><?= t('admin.templates.name_ar_col') ?></th><th><?= t('admin.qe.multiplier') ?></th><th><?= t('common.sort_order') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($qualityTiers as $qt): $fid = 'quality-tier-' . $qt['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/quick-estimate/quality-tiers/<?= $qt['id'] ?>"><?= csrf_field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="name_en" value="<?= e($qt['name_en']) ?>" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="name_ar" value="<?= e($qt['name_ar']) ?>" dir="rtl" style="min-width:140px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="multiplier" value="<?= e((string)$qt['multiplier']) ?>" style="width:80px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= e((string)$qt['sort_order']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="checkbox" name="is_active" value="1" <?= $qt['is_active'] ? 'checked' : '' ?>></td>
      <td style="display:flex;gap:6px;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/quick-estimate/quality-tiers/<?= $qt['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.qe.delete_quality_tier_confirm') ?>');" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

@endsection
