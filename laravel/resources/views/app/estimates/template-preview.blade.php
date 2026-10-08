@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <a href="/app/estimates/new" class="help-text">← Back</a>
    <h1 style="margin-top:6px;display:flex;align-items:center;gap:10px;"><span><?= e($template['icon']) ?></span> <?= e(local($template, 'name_en', 'name_ar')) ?></h1>
    <p class="help-text" style="margin-top:2px;"><?= e(local($template, 'description_en', 'description_ar')) ?></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
  <div class="card" style="padding:0;overflow:hidden;">
    <table class="data" style="border:none;">
      <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.type') ?></th><th><?= t('common.qty') ?></th><th>UOM</th><th><?= t('common.unit_cost') ?></th></tr></thead>
      <tbody>
      <?php $lastSection = null; foreach ($items as $it): ?>
        <?php if ($it['section_number'] !== $lastSection): $lastSection = $it['section_number']; ?>
          <tr style="background:#fafcfb;"><td colspan="5"><strong><?= e($it['section_number']) ?> <?= e(local($it, 'section_title_en', 'section_title_ar')) ?></strong></td></tr>
        <?php endif; ?>
        <tr>
          <td><?= e($it['item_number']) ?> <?= e(local($it, 'description_en', 'description_ar')) ?></td>
          <td><span class="badge badge-<?= $it['item_type'] === 'labor' ? 'yellow' : 'gray' ?>"><?= ucfirst($it['item_type']) ?></span></td>
          <td>0 <span class="help-text">(<?= e(rtrim(rtrim(number_format((float)$it['default_qty'], 2), '0'), '.')) ?>)</span></td>
          <td><?= e($it['uom']) ?></td>
          <td><?= money((float)$it['unit_cost']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" action="/app/estimates/templates/<?= $template['id'] ?>" class="card" style="position:sticky;top:90px;">
    <?= csrf_field() ?>
    <h3 style="font-size:14px;"><?= t('user.estimates.configuration') ?></h3>

    <div class="form-group">
      <label><input type="checkbox" name="include_quantities" value="1" style="width:auto;display:inline-block;"> <?= t('user.estimates.include_template_quantities') ?></label>
      <p class="help-text" style="margin-top:2px;"><?= t('user.estimates.include_quantities_hint') ?></p>
    </div>

    <div class="form-group"><label><?= t('common.description') ?></label><input type="text" name="title" value="<?= e(local($template, 'name_en', 'name_ar')) ?>"></div>

    <div class="form-group">
      <label><?= t('common.building_type') ?></label>
      <select name="building_type">
        <option value=""><?= t('user.estimates.building_type_placeholder') ?></option>
        <?php foreach ($buildingTypes as $bt): ?>
          <option value="<?= e($bt['name']) ?>" <?= $bt['name'] === $template['building_type'] ? 'selected' : '' ?>><?= e($bt['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($buildingTypes)): ?><p class="help-text"><?= t('user.estimates.no_building_types_hint') ?> <a href="/app/business-setup/building-types" target="_blank"><?= t('user.business_setup.title') ?></a>.</p><?php endif; ?>
    </div>

    <div class="form-group"><label><?= t('user.estimates.job_address') ?></label><input type="text" name="job_address" placeholder="Job address or suburb…"></div>

    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id">
        <option value=""><?= t('user.estimates.client_name_placeholder') ?></option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="total-row" style="font-size:15px;"><?= t('user.estimates.template_value') ?> <?= money($subtotal) ?></div>

    <div style="display:flex;gap:8px;margin-top:14px;">
      <a href="/app/estimates/new" class="btn btn-light" style="flex:1;text-align:center;"><?= t('common.cancel') ?></a>
      <button type="submit" class="btn btn-primary" style="flex:2;"><?= t('user.estimates.create_estimate') ?></button>
    </div>
  </form>
</div>

@endsection
