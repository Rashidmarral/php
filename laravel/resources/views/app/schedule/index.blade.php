@extends('layouts.app')

@section('content')
<?php
  // Grouped for the "Add a dependency" form's JS-driven task pickers (filtered by
  // the project selected in that same form — a dependency only ever makes sense
  // between two tasks in the same project).
  $tasksByProjectForDeps = [];
  $taskTitleById = [];
  foreach ($tasks as $t) {
      $tasksByProjectForDeps[$t['project_id']][] = ['id' => (int) $t['id'], 'label' => local($t, 'title')];
      $taskTitleById[$t['id']] = local($t, 'title');
  }
  // Grouped for the List view's "Depends on" column: each of this task's
  // predecessors, with its title and lag_days, plus the dependency row's own
  // id so the chip's delete button can target it.
  $dependenciesByTaskId = [];
  foreach ($dependencies as $dep) {
      $dependenciesByTaskId[$dep['task_id']][] = [
          'dependency_id' => $dep['id'],
          'title' => $taskTitleById[$dep['depends_on_task_id']] ?? '#'.$dep['depends_on_task_id'],
          'lag_days' => (int) $dep['lag_days'],
      ];
  }
?>
<div class="page-head">
  <h1><?= t('user.schedule.title') ?></h1>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.schedule.add_task') ?></h3>
  <?php if (empty($projects)): ?>
    <p class="help-text"><?= t('user.schedule.create_project_first') ?></p>
  <?php else: ?>
    <form method="post" action="/app/schedule" class="form-row" style="align-items:end;grid-template-columns:1.4fr 1.4fr 1fr 1fr 1fr auto;">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;">
        <label><?= t('user.schedule.task_en') ?></label>
        <input type="text" name="title" required placeholder="e.g. Electrical rough-in">
      </div>
      <div class="form-group" style="margin:0;">
        <label><?= t('user.schedule.task_ar') ?></label>
        <input type="text" name="title_ar" dir="rtl" placeholder="اسم المهمة بالعربية">
      </div>
      <div class="form-group" style="margin:0;">
        <label><?= t('common.project') ?></label>
        <select name="project_id" required>
          <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;"><label><?= t('common.start') ?></label><input type="date" name="start_date"></div>
      <div class="form-group" style="margin:0;"><label><?= t('common.end') ?></label><input type="date" name="end_date"></div>
      <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
    </form>
  <?php endif; ?>
</div>

<?php if (!empty($tasks) && !empty($projects)): ?>
<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.schedule.add_dependency') ?></h3>
  <form method="post" action="/app/schedule/dependencies" class="form-row" id="dependency-form" style="align-items:end;grid-template-columns:1.2fr 1.4fr 1.4fr .8fr auto;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.project') ?></label>
      <select id="dependency-project">
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.schedule.this_task') ?></label>
      <select name="task_id" id="dependency-task" required></select>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.schedule.depends_on') ?></label>
      <select name="depends_on_task_id" id="dependency-depends-on" required></select>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.schedule.lag_days') ?></label>
      <input type="number" name="lag_days" id="dependency-lag" min="0" step="1" value="0">
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
  <script>
  (function() {
    var tasksByProject = <?= json_encode($tasksByProjectForDeps, JSON_HEX_TAG) ?>;
    var selectText = <?= json_encode(t('user.schedule.select_task')) ?>;
    var projectSelect = document.getElementById('dependency-project');
    var taskSelect = document.getElementById('dependency-task');
    var dependsOnSelect = document.getElementById('dependency-depends-on');

    function fillOptions(select, tasks) {
      select.innerHTML = '';
      var placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = selectText;
      select.appendChild(placeholder);
      (tasks || []).forEach(function(t) {
        var opt = document.createElement('option');
        opt.value = t.id;
        opt.textContent = t.label;
        select.appendChild(opt);
      });
    }

    function refresh() {
      var tasks = tasksByProject[projectSelect.value] || [];
      fillOptions(taskSelect, tasks);
      fillOptions(dependsOnSelect, tasks);
    }

    projectSelect.addEventListener('change', refresh);
    refresh();
  })();
  </script>
</div>
<?php endif; ?>

<?php if (empty($tasks)): ?>
  <div class="card empty-state">
    <div class="icon">📅</div>
    <h3><?= t('user.schedule.no_tasks_title') ?></h3>
    <p><?= t('user.schedule.no_tasks_hint') ?></p>
  </div>
<?php else: ?>
  <div class="tabs">
    <a href="#" class="view-tab active" data-view="gantt">📊 Gantt</a>
    <a href="#" class="view-tab" data-view="list">📋 List</a>
  </div>

  <div id="view-gantt">
    <?php
      $dayWidth = 32;
      $labelWidth = 220;
      $dayCount = count($gantt['days']);
      $today = now()->format('Y-m-d');

      // Fixed row heights (locked in public/assets/css/app.css via height +
      // box-sizing:border-box on .gantt-head-label/.gantt-day/.gantt-project-row/
      // .gantt-task-cell) so the dependency-line overlay below can compute each
      // bar's pixel position from the same numbers the grid actually renders at,
      // instead of guessing at content-driven auto row heights.
      $headerRowHeight = 34;
      $projectRowHeight = 34;
      $taskRowHeight = 38;

      // Running top-of-row offset (px) and, per task id, the rendered bar's
      // left edge / width / vertical center — filled in as each row is drawn
      // below, then used after the grid to draw predecessor→successor lines.
      $rowTop = $headerRowHeight;
      $barPos = [];
    ?>
    <div class="gantt-wrap">
      <div class="gantt-grid" style="grid-template-columns:<?= $labelWidth ?>px repeat(<?= $dayCount ?>, <?= $dayWidth ?>px);grid-auto-rows:min-content;position:relative;min-width:<?= $labelWidth + $dayCount * $dayWidth ?>px;">
        <div class="gantt-head-label"><?= t('user.dashboard.task_col') ?></div>
        <?php foreach ($gantt['days'] as $d): ?>
          <div class="gantt-day<?= in_array($d->dayOfWeek, [5,6], true) ? ' weekend' : '' ?>">
            <span class="dow"><?= $d->format('D') ?></span><?= $d->format('j') ?>
          </div>
        <?php endforeach; ?>

        <?php foreach ($gantt['byProject'] as $projectId => $group): ?>
          <div class="gantt-project-row"><?= e(app()->getLocale() === 'ar' && !empty($group['project_name_ar']) ? $group['project_name_ar'] : $group['project_name']) ?></div>
          <?php $rowTop += $projectRowHeight; ?>
          <?php foreach ($group['tasks'] as $t): ?>
            <div class="gantt-row-label" title="<?= e(local($t, 'title')) ?>"><?= e(local($t, 'title')) ?></div>
            <?php
              $hasDates = !empty($t['start_date']) && !empty($t['end_date']);
              $startOffset = $hasDates ? $gantt['rangeStart']->diffInDays(\Carbon\Carbon::parse($t['start_date']), false) : 0;
              $duration = $hasDates ? max(1, \Carbon\Carbon::parse($t['start_date'])->diffInDays(\Carbon\Carbon::parse($t['end_date'])) + 1) : $dayCount;
              $isCritical = !empty($t['is_critical']);
              $floatLabel = $t['float'] !== null ? ' — '.t('user.schedule.float_col').': '.$t['float'] : '';
            ?>
            <div class="gantt-task-cell" style="grid-column: 2 / span <?= $dayCount ?>;">
              <?php if ($hasDates && $startOffset >= 0 && $startOffset < $dayCount): ?>
                <?php
                  $barLeft = $startOffset * $dayWidth + 3;
                  $barWidthPx = min($duration * $dayWidth, ($dayCount - $startOffset) * $dayWidth) - 6;
                  // Left edge of the task-cell's own grid column (column 2) starts right
                  // after the label column, so the bar's absolute x within the grid is the
                  // label width plus its own margin-inline-start.
                  $barPos[$t['id']] = [
                      'x1' => $labelWidth + $barLeft,
                      'x2' => $labelWidth + $barLeft + $barWidthPx,
                      'y' => $rowTop + $taskRowHeight / 2,
                  ];
                ?>
                <div class="gantt-bar status-<?= $t['status'] ?><?= $isCritical ? ' critical' : '' ?>" style="margin-inline-start:<?= $barLeft ?>px;width:<?= $barWidthPx ?>px;" title="<?= e(local($t, 'title')) ?>: <?= e($t['start_date']) ?> → <?= e($t['end_date']) ?><?= $isCritical ? ' — '.e(t('user.schedule.critical_path')) : '' ?><?= e($floatLabel) ?>">
                  <?= e(local($t, 'title')) ?>
                </div>
              <?php endif; ?>
            </div>
            <?php $rowTop += $taskRowHeight; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>

        <?php
          // Dependency connector overlay: a straight line from each predecessor
          // bar's right edge to its successor bar's left edge, using the exact
          // pixel positions captured while drawing the bars above. An edge is
          // skipped when either end isn't currently a rendered bar (outside the
          // visible day range, or missing dates) — nothing to point at.
          $gridHeight = $rowTop;
          $gridWidth = $labelWidth + $dayCount * $dayWidth;
        ?>
        <svg class="gantt-deps-overlay" width="<?= $gridWidth ?>" height="<?= $gridHeight ?>" viewBox="0 0 <?= $gridWidth ?> <?= $gridHeight ?>">
          <?php foreach ($dependencies as $dep): ?>
            <?php if (!isset($barPos[$dep['task_id']]) || !isset($barPos[$dep['depends_on_task_id']])): ?>
              <?php continue; ?>
            <?php endif; ?>
            <?php $from = $barPos[$dep['depends_on_task_id']]; ?>
            <?php $to = $barPos[$dep['task_id']]; ?>
            <line class="gantt-dep-line" x1="<?= $from['x2'] ?>" y1="<?= $from['y'] ?>" x2="<?= $to['x1'] ?>" y2="<?= $to['y'] ?>" />
          <?php endforeach; ?>
        </svg>
      </div>
    </div>
    <p class="help-text" style="margin-top:8px;">Bar color: gray = pending, gold = in progress, green = done. Bold red border = on the critical path. Red lines connect dependent tasks.</p>
  </div>

  <div id="view-list" style="display:none;">
  <table class="data">
    <thead><tr><th><?= t('user.dashboard.task_col') ?></th><th><?= t('common.project') ?></th><th><?= t('common.start') ?></th><th><?= t('user.projects.end_col') ?></th><th><?= t('user.schedule.depends_on_col') ?></th><th><?= t('user.schedule.float_col') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?= e(local($t, 'title')) ?><?= !empty($t['is_critical']) ? ' <span class="float-badge zero">'.e(t('user.schedule.critical_path')).'</span>' : '' ?></td>
        <td><a href="/app/projects/<?= $t['project_id'] ?>"><?= e(local($t, 'project_name')) ?></a></td>
        <td><?= e($t['start_date']) ?></td>
        <td><?= e($t['end_date']) ?></td>
        <td>
          <?php if (empty($dependenciesByTaskId[$t['id']])): ?>
            <span class="help-text"><?= t('user.schedule.no_dependencies') ?></span>
          <?php else: ?>
            <?php foreach ($dependenciesByTaskId[$t['id']] as $d): ?>
              <span class="dependency-chip">
                <?= e($d['title']) ?><?= $d['lag_days'] > 0 ? ' (+'.$d['lag_days'].'d)' : '' ?>
                <form method="post" action="/app/schedule/dependencies/<?= $d['dependency_id'] ?>/delete" onsubmit="return confirm('<?= t('user.schedule.remove_dependency_confirm') ?>');">
                  <?= csrf_field() ?>
                  <button type="submit" title="<?= t('common.delete') ?>">&times;</button>
                </form>
              </span>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
        <td>
          <?php if ($t['float'] !== null): ?>
            <span class="float-badge<?= $t['float'] === 0 ? ' zero' : '' ?>"><?= $t['float'] ?></span>
          <?php else: ?>
            <span class="help-text">—</span>
          <?php endif; ?>
        </td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/status" style="display:inline;">
            <?= csrf_field() ?>
            <select name="status" onchange="this.form.submit()" class="badge badge-<?= $t['status']==='done'?'green':($t['status']==='in_progress'?'yellow':'gray') ?>" style="border:none;padding:4px 8px;">
              <option value="pending" <?= $t['status']==='pending'?'selected':'' ?>><?= t('user.schedule.status_pending') ?></option>
              <option value="in_progress" <?= $t['status']==='in_progress'?'selected':'' ?>><?= t('user.schedule.status_in_progress') ?></option>
              <option value="done" <?= $t['status']==='done'?'selected':'' ?>><?= t('user.schedule.status_done') ?></option>
            </select>
          </form>
        </td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/delete" onsubmit="return confirm('<?= t('user.schedule.remove_task_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>

  <script>
  (function() {
    document.querySelectorAll('.view-tab').forEach(function(tab) {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        document.querySelectorAll('.view-tab').forEach(function(t) { t.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById('view-gantt').style.display = tab.dataset.view === 'gantt' ? '' : 'none';
        document.getElementById('view-list').style.display = tab.dataset.view === 'list' ? '' : 'none';
      });
    });
  })();
  </script>
<?php endif; ?>

@endsection
