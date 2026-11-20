@extends('layouts.app')

@section('content')
<?php
$qtyOnHand = (float) ($material['qty_on_hand'] ?? 0);
$reorderLevel = $material['reorder_level'] ?? null;
$lowStock = $reorderLevel !== null && $qtyOnHand <= (float) $reorderLevel;
?>
<div class="page-head">
  <div>
    <h1><?= e(local($material, 'name')) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.materials.stock_title') ?></p>
  </div>
  <a href="/app/materials" class="btn btn-light"><?= t('user.materials.back_to_materials') ?></a>
</div>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label"><?= t('user.materials.qty_on_hand') ?></div>
    <div class="value"><?= number_format($qtyOnHand, 2) ?> <?= e($material['unit']) ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('user.materials.reorder_level') ?></div>
    <div class="value"><?= $reorderLevel !== null ? number_format((float) $reorderLevel, 2) . ' ' . e($material['unit']) : '—' ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('common.status') ?></div>
    <div class="value" style="font-size:16px;">
      <?php if ($lowStock): ?>
        <span class="badge badge-red"><?= t('user.materials.low_stock') ?></span>
      <?php else: ?>
        <span class="badge badge-green"><?= t('user.materials.in_stock') ?></span>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid grid-2" style="margin-bottom:20px;align-items:start;">
  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.materials.record_movement') ?></h3>
    <form method="post" action="/app/materials/<?= $material['id'] ?>/stock/movements">
      <?= csrf_field() ?>
      <div class="form-group">
        <label><?= t('common.type') ?></label>
        <select name="type" id="movement-type" required>
          <option value="receive"><?= t('user.materials.type_receive') ?></option>
          <option value="issue"><?= t('user.materials.type_issue') ?></option>
          <option value="adjustment"><?= t('user.materials.type_adjustment') ?></option>
        </select>
      </div>
      <div class="form-group" id="direction-group" style="display:none;">
        <label><?= t('user.materials.direction') ?></label>
        <select name="direction">
          <option value="up"><?= t('user.materials.direction_up') ?></option>
          <option value="down"><?= t('user.materials.direction_down') ?></option>
        </select>
      </div>
      <div class="form-group" id="project-group">
        <label><?= t('user.materials.issue_to_project') ?></label>
        <select name="project_id">
          <option value=""><?= t('common.none') ?></option>
          <?php foreach ($allProjects as $p): ?><option value="<?= $p->id ?>"><?= e($p->name) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= t('common.quantity') ?> (<?= e($material['unit']) ?>)</label>
        <input type="number" step="0.01" min="0.01" name="qty" required>
      </div>
      <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="note"></div>
      <button type="submit" class="btn btn-primary"><?= t('user.materials.record_movement') ?></button>
    </form>
  </div>

  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.materials.about_this_material') ?></h3>
    <p class="help-text"><?= t('common.category') ?>: <?= e($material['category'] ?: '—') ?></p>
    <p class="help-text"><?= t('common.unit') ?>: <?= e($material['unit']) ?></p>
    <p class="help-text"><?= t('common.rate') ?>: <?= money((float) $material['unit_cost']) ?>/<?= e($material['unit']) ?></p>
    <a href="/app/materials/<?= $material['id'] ?>/edit" class="btn btn-outline btn-sm"><?= t('common.edit') ?></a>
  </div>
</div>

<?php if (empty($movements)): ?>
  <div class="card empty-state">
    <div class="icon">📦</div>
    <h3><?= t('user.materials.no_movements_title') ?></h3>
    <p><?= t('user.materials.no_movements_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.date') ?></th><th><?= t('common.type') ?></th><th><?= t('common.quantity') ?></th><th><?= t('common.project') ?></th><th><?= t('common.notes') ?></th></tr></thead>
    <tbody>
    <?php foreach ($movements as $mv):
      $isUp = $mv['type'] === 'receive' || ($mv['type'] === 'adjustment' && $mv['direction'] === 'up');
      $typeLabel = $types[$mv['type']] ?? ucfirst($mv['type']);
      if ($mv['type'] === 'adjustment' && $mv['direction']) {
          $typeLabel .= ' (' . ($directions[$mv['direction']] ?? ucfirst($mv['direction'])) . ')';
      }
    ?>
      <tr>
        <td><?= e($mv['created_at']) ?></td>
        <td><span class="badge badge-<?= $isUp ? 'green' : 'yellow' ?>"><?= e($typeLabel) ?></span></td>
        <td><?= $isUp ? '+' : '-' ?><?= number_format((float) $mv['qty'], 2) ?> <?= e($material['unit']) ?></td>
        <td><?= e($mv['project_id'] ? ($projectNames[$mv['project_id']] ?? '—') : '—') ?></td>
        <td><?= e($mv['note'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<script>
(function() {
  const typeSelect = document.getElementById('movement-type');
  const directionGroup = document.getElementById('direction-group');
  const projectGroup = document.getElementById('project-group');
  function apply() {
    directionGroup.style.display = typeSelect.value === 'adjustment' ? '' : 'none';
    projectGroup.style.display = typeSelect.value === 'issue' ? '' : 'none';
  }
  typeSelect.addEventListener('change', apply);
  apply();
})();
</script>

@endsection
