@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= e($template['icon']) ?> <?= e($template['name_en']) ?> — <?= t('admin.templates.line_items') ?></h1>
  <a href="/admin/estimate-templates" class="btn btn-light">← <?= t('admin.templates.back_to_templates') ?></a>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.templates.line_items_hint') ?> <strong><?= money($subtotal) ?></strong>.</p>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.templates.add_item') ?></h3>
  <form method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items">
    <?= csrf_field() ?>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="form-group"><label><?= t('admin.templates.section_number') ?></label><input type="text" name="section_number" value="1.0" placeholder="e.g. 2.1"></div>
      <div class="form-group"><label><?= t('admin.templates.section_title_en') ?></label><input type="text" name="section_title_en"></div>
      <div class="form-group"><label><?= t('admin.templates.section_title_ar') ?></label><input type="text" name="section_title_ar" dir="rtl"></div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr;">
      <div class="form-group"><label><?= t('admin.templates.item_number') ?></label><input type="text" name="item_number" value="1.1"></div>
      <div class="form-group"><label><?= t('common.description_en') ?></label><input type="text" name="description_en" required></div>
      <div class="form-group"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl"></div>
    </div>
    <div class="form-row" style="grid-template-columns:1fr 1fr 1fr 1fr 1fr;">
      <div class="form-group">
        <label><?= t('common.type') ?></label>
        <select name="item_type">
          <option value="material"><?= t('admin.templates.item_type_material') ?></option>
          <option value="labor"><?= t('admin.templates.item_type_labor') ?></option>
          <option value="equipment"><?= t('admin.templates.item_type_equipment') ?></option>
          <option value="subcontract"><?= t('admin.templates.item_type_subcontract') ?></option>
        </select>
      </div>
      <div class="form-group"><label><?= t('admin.templates.default_qty') ?></label><input type="number" step="0.01" name="default_qty" value="0"></div>
      <div class="form-group"><label><?= t('admin.templates.unit_uom') ?></label><input type="text" name="uom" value="each"></div>
      <div class="form-group"><label><?= t('common.unit_cost') ?> (SAR)</label><input type="number" step="0.01" name="unit_cost" value="0"></div>
      <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('admin.templates.add_item') ?></button>
  </form>
</div>

<div style="overflow-x:auto;">
<table class="data">
  <thead><tr>
    <th><?= t('admin.templates.sec_short') ?></th><th><?= t('admin.templates.section_title') ?></th><th><?= t('admin.templates.item_short') ?></th><th><?= t('common.description') ?></th><th><?= t('common.type') ?></th><th><?= t('common.qty') ?></th><th><?= t('admin.templates.unit_uom') ?></th><th><?= t('common.unit_cost') ?></th><th><?= t('common.sort_order') ?></th><th></th>
  </tr></thead>
  <tbody>
  <?php foreach ($items as $it): $fid = 'item-' . $it['id']; ?>
    <form id="<?= $fid ?>" method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items/<?= $it['id'] ?>"><?= csrf_field() ?></form>
    <tr>
      <td><input form="<?= $fid ?>" type="text" name="section_number" value="<?= e($it['section_number']) ?>" style="width:60px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="section_title_en" value="<?= e($it['section_title_en']) ?>" style="min-width:130px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="item_number" value="<?= e($it['item_number']) ?>" style="width:60px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="description_en" value="<?= e($it['description_en']) ?>" style="min-width:200px;"></td>
      <td>
        <select form="<?= $fid ?>" name="item_type" style="width:110px;">
          <?php foreach (['material'=>t('admin.templates.item_type_material'),'labor'=>t('admin.templates.item_type_labor'),'equipment'=>t('admin.templates.item_type_equipment'),'subcontract'=>t('admin.templates.item_type_subcontract')] as $val => $label): ?>
            <option value="<?= $val ?>" <?= $it['item_type'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="default_qty" value="<?= e((string)$it['default_qty']) ?>" style="width:80px;"></td>
      <td><input form="<?= $fid ?>" type="text" name="uom" value="<?= e($it['uom']) ?>" style="width:70px;"></td>
      <td><input form="<?= $fid ?>" type="number" step="0.01" name="unit_cost" value="<?= e((string)$it['unit_cost']) ?>" style="width:90px;"></td>
      <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= e((string)$it['sort_order']) ?>" style="width:70px;"></td>
      <td style="display:flex;gap:6px;white-space:nowrap;">
        <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
        <form method="post" action="/admin/estimate-templates/<?= $template['id'] ?>/items/<?= $it['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.templates.delete_item_confirm') ?>');" style="display:inline;">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($items)): ?>
    <tr><td colspan="10" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.templates.no_items') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
</div>

@endsection
