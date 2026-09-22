<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('user.schedule.title') ?></h1>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.schedule.add_task') ?></h3>
  <?php if (empty($projects)): ?>
    <p class="help-text"><?= t('user.schedule.create_project_first') ?></p>
  <?php else: ?>
    <form method="post" action="/app/schedule" class="form-row" style="align-items:end;grid-template-columns:1.4fr 1.4fr 1fr 1fr 1fr auto;">
      <?= Csrf::field() ?>
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
          <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
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
  <table class="data">
    <thead><tr><th><?= t('user.dashboard.task_col') ?></th><th><?= t('common.project') ?></th><th><?= t('common.start') ?></th><th><?= t('user.projects.end_col') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tasks as $t): ?>
      <tr>
        <td><?= View::e(View::local($t, 'title')) ?></td>
        <td><a href="/app/projects/<?= $t['project_id'] ?>"><?= View::e(View::local($t, 'project_name')) ?></a></td>
        <td><?= View::e($t['start_date']) ?></td>
        <td><?= View::e($t['end_date']) ?></td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/status" style="display:inline;">
            <?= Csrf::field() ?>
            <select name="status" onchange="this.form.submit()" class="badge badge-<?= $t['status']==='done'?'green':($t['status']==='in_progress'?'yellow':'gray') ?>" style="border:none;padding:4px 8px;">
              <option value="pending" <?= $t['status']==='pending'?'selected':'' ?>><?= t('user.schedule.status_pending') ?></option>
              <option value="in_progress" <?= $t['status']==='in_progress'?'selected':'' ?>><?= t('user.schedule.status_in_progress') ?></option>
              <option value="done" <?= $t['status']==='done'?'selected':'' ?>><?= t('user.schedule.status_done') ?></option>
            </select>
          </form>
        </td>
        <td>
          <form method="post" action="/app/schedule/<?= $t['id'] ?>/delete" onsubmit="return confirm('<?= t('user.schedule.remove_task_confirm') ?>');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
