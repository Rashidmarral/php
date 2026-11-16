@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.equipment.calendar_title') ?></h1>
  <a href="/app/equipment" class="btn btn-light"><?= t('user.equipment.back_to_equipment') ?></a>
</div>

<?php if (empty($equipment)): ?>
  <div class="card empty-state">
    <div class="icon">📅</div>
    <h3><?= t('user.equipment.no_equipment_title') ?></h3>
    <p><?= t('user.equipment.no_equipment_hint') ?></p>
    <a href="/app/equipment/create" class="btn btn-primary"><?= t('user.equipment.new') ?></a>
  </div>
<?php else: ?>
  <style>
    .gantt-bar-wrap > summary { list-style: none; cursor: pointer; }
    .gantt-bar-wrap > summary::-webkit-details-marker { display: none; }
    .gantt-bar-wrap .reschedule-panel { margin: 4px 3px 8px; }
  </style>
  <?php
    $dayWidth = 32;
    $labelWidth = 220;
    $dayCount = count($gantt['days']);
  ?>
  <div class="gantt-wrap">
    <div class="gantt-grid" style="grid-template-columns:<?= $labelWidth ?>px repeat(<?= $dayCount ?>, <?= $dayWidth ?>px);grid-auto-rows:min-content;position:relative;min-width:<?= $labelWidth + $dayCount * $dayWidth ?>px;">
      <div class="gantt-head-label"><?= t('user.equipment.calendar_asset_col') ?></div>
      <?php foreach ($gantt['days'] as $d): ?>
        <div class="gantt-day<?= in_array($d->dayOfWeek, [5, 6], true) ? ' weekend' : '' ?>">
          <span class="dow"><?= $d->format('D') ?></span><?= $d->format('j') ?>
        </div>
      <?php endforeach; ?>

      <?php foreach ($equipment as $item): ?>
        <div class="gantt-row-label" title="<?= e(local($item, 'name')) ?>"><?= e(local($item, 'name')) ?></div>
        <div class="gantt-task-cell" style="grid-column: 2 / span <?= $dayCount ?>;">
          <?php foreach (($byEquipment[$item['id']] ?? []) as $a):
            $startOffset = $gantt['rangeStart']->diffInDays(\Carbon\Carbon::parse($a['assigned_date']), false);
            $endDate = $a['returned_date'] ?: now()->format('Y-m-d');
            $duration = max(1, \Carbon\Carbon::parse($a['assigned_date'])->diffInDays(\Carbon\Carbon::parse($endDate)) + 1);
            $statusClass = $a['returned_date'] ? 'status-done' : 'status-in_progress';
            $statusLabel = $a['returned_date'] ? t('user.equipment.calendar_status_returned') : t('user.equipment.calendar_status_active');
          ?>
            <?php if ($startOffset >= 0 && $startOffset < $dayCount): ?>
              <details class="gantt-bar-wrap" style="margin-inline-start:<?= $startOffset * $dayWidth + 3 ?>px;">
                <summary class="gantt-bar <?= $statusClass ?>" style="width:<?= min($duration * $dayWidth, ($dayCount - $startOffset) * $dayWidth) - 6 ?>px;" title="<?= e($a['project_name']) ?> (<?= e($statusLabel) ?>): <?= e($a['assigned_date']) ?> → <?= e($a['returned_date'] ?: $statusLabel) ?>">
                  <?= e($a['project_name']) ?>
                </summary>
                <div class="card reschedule-panel" style="max-width:280px;">
                  <p class="help-text" style="margin-top:0;"><?= e($a['project_name']) ?> — <?= e($statusLabel) ?></p>
                  <form method="post" action="/app/equipment-assignments/<?= $a['id'] ?>/reschedule">
                    <?= csrf_field() ?>
                    <div class="form-group" style="margin-bottom:8px;">
                      <label><?= t('user.equipment.assigned_date') ?></label>
                      <input type="date" name="assigned_date" value="<?= e($a['assigned_date']) ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:8px;">
                      <label><?= t('user.equipment.returned_date') ?></label>
                      <input type="date" name="returned_date" value="<?= e($a['returned_date'] ?? '') ?>">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><?= t('user.equipment.calendar_reschedule_save') ?></button>
                  </form>
                </div>
              </details>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <p class="help-text" style="margin-top:8px;"><?= t('user.equipment.calendar_legend') ?></p>
<?php endif; ?>

@endsection
