@extends('layouts.app')

@section('content')
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
          <?php foreach ($group['tasks'] as $t): ?>
            <div class="gantt-row-label" title="<?= e(local($t, 'title')) ?>"><?= e(local($t, 'title')) ?></div>
            <?php
              $hasDates = !empty($t['start_date']) && !empty($t['end_date']);
              $startOffset = $hasDates ? $gantt['rangeStart']->diffInDays(\Carbon\Carbon::parse($t['start_date']), false) : 0;
              $duration = $hasDates ? max(1, \Carbon\Carbon::parse($t['start_date'])->diffInDays(\Carbon\Carbon::parse($t['end_date'])) + 1) : $dayCount;
            ?>
            <div class="gantt-task-cell" style="grid-column: 2 / span <?= $dayCount ?>;">
              <?php if ($hasDates && $startOffset >= 0 && $startOffset < $dayCount): ?>
                <div class="gantt-bar status-<?= $t['status'] ?>" style="margin-inline-start:<?= $startOffset * $dayWidth + 3 ?>px;width:<?= min($duration * $dayWidth, ($dayCount - $startOffset) * $dayWidth) - 6 ?>px;" title="<?= e(local($t, 'title')) ?>: <?= e($t['start_date']) ?> → <?= e($t['end_date']) ?>">
                  <?= e(local($t, 'title')) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <p class="help-text" style="margin-top:8px;">Bar color: gray = pending, gold = in progress, green = done.</p>
  </div>

  <div id="view-list" style="display:none;">
  <table class="data">
    <thead><tr><th><?= t('user.dashboard.task_col') ?></th><th><?= t('common.project') ?></th><th><?= t('common.start') ?></th><th><?= t('user.projects.end_col') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?= e(local($t, 'title')) ?></td>
        <td><a href="/app/projects/<?= $t['project_id'] ?>"><?= e(local($t, 'project_name')) ?></a></td>
        <td><?= e($t['start_date']) ?></td>
        <td><?= e($t['end_date']) ?></td>
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
