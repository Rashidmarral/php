@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/equipment">&larr; <?= t('user.equipment.title') ?></a></p>
    <h1><?= e(local($equipment, 'name')) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?php if ($equipment['category']): ?><?= e($equipment['category']) ?> · <?php endif; ?>
      <?= $equipment['asset_number'] ? '#' . e($equipment['asset_number']) : t('user.equipment.no_asset_number') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php $statusBadge = ['available' => 'green', 'in_use' => 'blue', 'under_maintenance' => 'yellow', 'retired' => 'gray'][$equipment['status']] ?? 'gray'; ?>
    <span class="badge badge-<?= $statusBadge ?>" style="font-size:13px;padding:6px 14px;"><?= e($statuses[$equipment['status']] ?? ucfirst($equipment['status'])) ?></span>
    <a href="/app/equipment/<?= $equipment['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <?php if (!$hasHistory && auth()->user()->can('write')): ?>
      <form method="post" action="/app/equipment/<?= $equipment['id'] ?>/delete" onsubmit="return confirm('<?= t('user.equipment.remove_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.equipment.ownership_type') ?></div><div class="value" style="font-size:18px;"><?= e($ownershipTypes[$equipment['ownership_type']] ?? ucfirst($equipment['ownership_type'])) ?></div></div>
  <div class="kpi"><div class="label"><?= t('common.supplier') ?></div><div class="value" style="font-size:18px;"><?= $supplier ? e($supplier->name) : '—' ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.equipment.purchase_cost') ?></div><div class="value" style="font-size:18px;"><?= $equipment['purchase_cost'] !== null ? money((float)$equipment['purchase_cost']) : '—' ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.equipment.rental_cost_per_day') ?></div><div class="value" style="font-size:18px;"><?= $equipment['rental_cost_per_day'] !== null ? money((float)$equipment['rental_cost_per_day']) : '—' ?></div></div>
</div>

<?php if (!empty($equipment['notes'])): ?>
  <div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:14px;"><?= t('common.notes') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($equipment['notes']) ?></p>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.equipment.assignments_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.equipment.assignments_hint') ?></p>

  <?php if ($activeAssignment): ?>
    <div class="alert" style="background:#eef3fb;color:#2c5282;border:1px solid #bcd6f2;margin-bottom:16px;">
      <?= t('user.equipment.currently_assigned', ['project' => e($activeAssignment['project_name'] ?? '—')]) ?>
      <form method="post" action="/app/equipment-assignments/<?= $activeAssignment['id'] ?>/return" style="display:inline;margin-inline-start:10px;">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-outline"><?= t('user.equipment.return_btn') ?></button>
      </form>
    </div>
  <?php else: ?>
    <?php if (auth()->user()->can('write')): ?>
    <form method="post" action="/app/equipment/<?= $equipment['id'] ?>/assignments" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;flex:1;min-width:200px;">
        <label><?= t('common.project') ?></label>
        <select name="project_id" required>
          <option value=""><?= t('user.equipment.select_project') ?></option>
          <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e(local($p, 'name')) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;width:160px;"><label><?= t('user.equipment.assigned_date') ?></label><input type="date" name="assigned_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group" style="margin:0;flex:1;min-width:160px;"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
      <button type="submit" class="btn btn-primary"><?= t('user.equipment.assign_btn') ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>

  <?php if (empty($assignments)): ?>
    <p class="help-text"><?= t('user.equipment.no_assignments_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.project') ?></th><th><?= t('user.equipment.assigned_date') ?></th><th><?= t('user.equipment.returned_date') ?></th><th><?= t('common.notes') ?></th></tr></thead>
      <tbody>
      <?php foreach ($assignments as $a): ?>
        <tr>
          <td><?= e($a['project_name']) ?></td>
          <td><?= e($a['assigned_date'] ?: '—') ?></td>
          <td><?= $a['returned_date'] ? e($a['returned_date']) : '<span class="badge badge-blue">' . t('user.equipment.currently_assigned_badge') . '</span>' ?></td>
          <td><?= e($a['notes'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="font-size:14px;"><?= t('user.equipment.maintenance_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.equipment.maintenance_hint') ?></p>

  <?php if (auth()->user()->can('write')): ?>
  <form method="post" action="/app/equipment/<?= $equipment['id'] ?>/maintenance" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.equipment.maintenance_date') ?></label><input type="date" name="maintenance_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label><?= t('user.equipment.cost') ?></label><input type="number" step="0.01" min="0" name="cost"></div>
    </div>
    <div class="form-group"><label><?= t('user.equipment.description') ?></label><input type="text" name="description" required placeholder="e.g. Oil change, filter replacement"></div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.equipment.performed_by') ?></label><input type="text" name="performed_by" placeholder="e.g. Al Rajhi Equipment Services"></div>
      <div class="form-group"><label><?= t('user.equipment.next_due_date') ?></label><input type="date" name="next_due_date"></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('user.equipment.add_log_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($maintenanceLogs)): ?>
    <p class="help-text"><?= t('user.equipment.no_maintenance_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('user.equipment.maintenance_date') ?></th><th><?= t('user.equipment.description') ?></th><th><?= t('user.equipment.performed_by') ?></th><th><?= t('user.equipment.cost') ?></th><th><?= t('user.equipment.next_due_date') ?></th></tr></thead>
      <tbody>
      <?php foreach ($maintenanceLogs as $log): ?>
        <tr>
          <td><?= e($log['maintenance_date']) ?></td>
          <td><?= e($log['description']) ?></td>
          <td><?= e($log['performed_by'] ?: '—') ?></td>
          <td><?= $log['cost'] !== null ? money((float)$log['cost']) : '—' ?></td>
          <td><?= e($log['next_due_date'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
