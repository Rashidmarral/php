@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.equipment.title') ?></h1>
  <a href="/app/equipment/create" class="btn btn-primary"><?= t('user.equipment.new') ?></a>
</div>

<form method="get" action="/app/equipment" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
  <div class="form-group" style="margin:0;width:180px;">
    <label><?= t('common.status') ?></label>
    <select name="status" onchange="this.form.submit()">
      <option value=""><?= t('user.equipment.all_statuses') ?></option>
      <?php foreach ($statuses as $key => $label): ?>
        <option value="<?= $key ?>" <?= $filterStatus === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0;width:200px;">
    <label><?= t('common.category') ?></label>
    <select name="category" onchange="this.form.submit()">
      <option value=""><?= t('user.equipment.all_categories') ?></option>
      <?php foreach ($categories as $cat): ?>
        <option value="<?= e($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if (empty($equipment)): ?>
  <div class="card empty-state">
    <div class="icon">🚜</div>
    <h3><?= t('user.equipment.no_equipment_title') ?></h3>
    <p><?= t('user.equipment.no_equipment_hint') ?></p>
    <a href="/app/equipment/create" class="btn btn-primary"><?= t('user.equipment.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.category') ?></th><th><?= t('user.equipment.ownership_type') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($equipment as $item):
      $statusBadge = ['available' => 'green', 'in_use' => 'blue', 'under_maintenance' => 'yellow', 'retired' => 'gray'][$item['status']] ?? 'gray';
    ?>
      <tr>
        <td>
          <a href="/app/equipment/<?= $item['id'] ?>"><?= e(local($item, 'name')) ?></a>
          <?php if ($item['asset_number']): ?><br><span class="help-text">#<?= e($item['asset_number']) ?></span><?php endif; ?>
        </td>
        <td><?php if ($item['category']): ?><span class="badge badge-gray"><?= e($item['category']) ?></span><?php endif; ?></td>
        <td><?= e(\App\Models\Equipment::OWNERSHIP_TYPES[$item['ownership_type']] ?? ucfirst($item['ownership_type'])) ?></td>
        <td><span class="badge badge-<?= $statusBadge ?>"><?= e($statuses[$item['status']] ?? ucfirst($item['status'])) ?></span></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/equipment/<?= $item['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a>
          <a href="/app/equipment/<?= $item['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
