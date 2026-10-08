<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= View::e($project['name']) ?></h1>
  <a href="/portal" class="btn btn-light">← Back</a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$project['status'])) ?></span></div></div>
  <div class="kpi"><div class="label">Start</div><div class="value" style="font-size:16px;"><?= View::e($project['start_date'] ?: '—') ?></div></div>
  <div class="kpi"><div class="label">End</div><div class="value" style="font-size:16px;"><?= View::e($project['end_date'] ?: '—') ?></div></div>
</div>

<?php if ($project['description']): ?>
  <div class="card" style="margin-bottom:20px;"><p><?= View::e($project['description']) ?></p></div>
<?php endif; ?>

<div class="card">
  <h3>Schedule</h3>
  <?php if (empty($tasks)): ?>
    <p class="help-text">No schedule published yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Task</th><th>Start</th><th>End</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($tasks as $t): ?>
        <tr><td><?= View::e($t['title']) ?></td><td><?= View::e($t['start_date']) ?></td><td><?= View::e($t['end_date']) ?></td><td><span class="badge badge-gray"><?= View::e(str_replace('_',' ',$t['status'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
