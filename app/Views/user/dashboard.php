<?php use App\Core\View; ?>
<?php if ($trialDaysLeft !== null): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:<?= $trialDaysLeft <= 3 ? '#fdf3e0' : 'var(--brand-light)' ?>;border-color:<?= $trialDaysLeft <= 3 ? '#e8c76b' : 'var(--brand)' ?>;">
    <div>
      <strong><?= $trialDaysLeft > 0 ? "Your trial ends in {$trialDaysLeft} day" . ($trialDaysLeft === 1 ? '' : 's') . '.' : 'Your trial has ended.' ?></strong>
      <?php if ($currentPlan): ?><span style="color:var(--muted);"> You're currently experiencing the <?= View::e($currentPlan['name']) ?> plan.</span><?php endif; ?>
    </div>
    <a href="/app/billing" class="btn btn-primary btn-sm">Subscribe now</a>
  </div>
<?php endif; ?>
<?php if (!empty($expiringDocs)): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:#fdf3e0;border-color:#e8c76b;">
    <div>
      <strong><?= count($expiringDocs) ?> compliance document<?= count($expiringDocs) === 1 ? '' : 's' ?> expiring soon.</strong>
      <span style="color:var(--muted);"> <?= View::e(implode(', ', array_column(array_slice($expiringDocs, 0, 3), 'name'))) ?><?= count($expiringDocs) > 3 ? '…' : '' ?></span>
    </div>
    <a href="/app/business-setup/compliance" class="btn btn-primary btn-sm">Review documents</a>
  </div>
<?php endif; ?>
<div class="page-head">
  <h1>Dashboard</h1>
  <a href="/app/projects/create" class="btn btn-primary">+ New Project</a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Active Projects</div><div class="value"><?= $activeProjects ?></div></div>
  <div class="kpi"><div class="label">Total Budget</div><div class="value"><?= View::money($totalBudget) ?></div></div>
  <div class="kpi"><div class="label">Outstanding</div><div class="value"><?= View::money($outstanding) ?></div></div>
  <div class="kpi"><div class="label">Paid This Month</div><div class="value"><?= View::money($paidThisMonth) ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Recent Projects</h3>
    <?php if (empty($recentProjects)): ?>
      <p class="help-text">No projects yet. <a href="/app/projects/create">Create your first project</a>.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Name</th><th>Status</th><th>Budget</th></tr></thead>
        <tbody>
        <?php foreach ($recentProjects as $p): ?>
          <tr>
            <td><a href="/app/projects/<?= $p['id'] ?>"><?= View::e($p['name']) ?></a></td>
            <td><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$p['status'])) ?></span></td>
            <td><?= View::money((float)$p['budget']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Upcoming Schedule</h3>
    <?php if (empty($upcomingTasks)): ?>
      <p class="help-text">No upcoming tasks.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Task</th><th>Start</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($upcomingTasks as $tk): ?>
          <tr>
            <td><?= View::e($tk['title']) ?></td>
            <td><?= View::e($tk['start_date']) ?></td>
            <td><span class="badge badge-<?= $tk['status'] === 'in_progress' ? 'yellow' : 'gray' ?>"><?= View::e(str_replace('_',' ',$tk['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Recent Estimates</h3>
  <?php if (empty($recentEstimates)): ?>
    <p class="help-text">No estimates yet. <a href="/app/estimates/new">Create one</a>.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Title</th><th>Status</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($recentEstimates as $e): ?>
        <tr>
          <td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e($e['title']) ?></a></td>
          <td><span class="badge badge-gray"><?= View::e($e['status']) ?></span></td>
          <td><?= View::money((float)$e['total']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
