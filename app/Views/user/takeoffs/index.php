<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Digital Takeoff</h1>
  <a href="/app/takeoffs/create" class="btn btn-primary">+ New Takeoff</a>
</div>

<?php if (empty($takeoffs)): ?>
  <div class="card empty-state">
    <div class="icon">📐</div>
    <h3>No takeoffs yet</h3>
    <p>Upload a plan, calibrate its scale, then click or trace to measure lengths, areas, and counts — send the results straight into an estimate.</p>
    <a href="/app/takeoffs/create" class="btn btn-primary">+ New Takeoff</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Project</th><th>Created</th></tr></thead>
    <tbody>
    <?php foreach ($takeoffs as $t): ?>
      <tr>
        <td><a href="/app/takeoffs/<?= $t['id'] ?>"><?= View::e($t['name']) ?></a></td>
        <td><?= View::e($t['project_name'] ?? '—') ?></td>
        <td class="help-text"><?= View::e($t['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
