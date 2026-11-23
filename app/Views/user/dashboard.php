<?php use App\Core\View; ?>
<?php if ($trialDaysLeft !== null): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:<?= $trialDaysLeft <= 3 ? '#fdf3e0' : 'var(--brand-light)' ?>;border-color:<?= $trialDaysLeft <= 3 ? '#e8c76b' : 'var(--brand)' ?>;">
    <div>
      <strong><?= $trialDaysLeft > 0 ? t($trialDaysLeft === 1 ? 'user.dashboard.trial_ends_singular' : 'user.dashboard.trial_ends_plural', ['days' => $trialDaysLeft]) : t('user.dashboard.trial_ended') ?></strong>
      <?php if ($currentPlan): ?><span style="color:var(--muted);"> <?= t('user.dashboard.currently_on_plan', ['plan' => View::e($currentPlan['name'])]) ?></span><?php endif; ?>
    </div>
    <a href="/app/billing" class="btn btn-primary btn-sm"><?= t('user.dashboard.subscribe_now') ?></a>
  </div>
<?php endif; ?>
<?php if (!empty($expiringDocs)): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:#fdf3e0;border-color:#e8c76b;">
    <div>
      <strong><?= t(count($expiringDocs) === 1 ? 'user.dashboard.docs_expiring_singular' : 'user.dashboard.docs_expiring_plural', ['count' => count($expiringDocs)]) ?></strong>
      <span style="color:var(--muted);"> <?= View::e(implode(', ', array_column(array_slice($expiringDocs, 0, 3), 'name'))) ?><?= count($expiringDocs) > 3 ? '…' : '' ?></span>
    </div>
    <a href="/app/business-setup/compliance" class="btn btn-primary btn-sm"><?= t('user.dashboard.review_documents') ?></a>
  </div>
<?php endif; ?>
<div class="page-head">
  <h1><?= t('user.dashboard.title') ?></h1>
  <a href="/app/projects/create" class="btn btn-primary"><?= t('user.dashboard.new_project') ?></a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.dashboard.active_projects') ?></div><div class="value"><?= $activeProjects ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.dashboard.total_budget') ?></div><div class="value"><?= View::money($totalBudget) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.dashboard.outstanding') ?></div><div class="value"><?= View::money($outstanding) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.dashboard.paid_this_month') ?></div><div class="value"><?= View::money($paidThisMonth) ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('user.dashboard.recent_projects') ?></h3>
    <?php if (empty($recentProjects)): ?>
      <p class="help-text"><?= t('user.dashboard.no_projects_yet') ?> <a href="/app/projects/create"><?= t('user.dashboard.create_first_project') ?></a>.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.status') ?></th><th><?= t('common.amount') ?></th></tr></thead>
        <tbody>
        <?php foreach ($recentProjects as $p): ?>
          <tr>
            <td><a href="/app/projects/<?= $p['id'] ?>"><?= View::e(View::local($p, 'name')) ?></a></td>
            <td><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$p['status'])) ?></span></td>
            <td><?= View::money((float)$p['budget']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3><?= t('user.dashboard.upcoming_schedule') ?></h3>
    <?php if (empty($upcomingTasks)): ?>
      <p class="help-text"><?= t('user.dashboard.no_upcoming_tasks') ?></p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th><?= t('user.dashboard.task_col') ?></th><th><?= t('common.start') ?></th><th><?= t('common.status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($upcomingTasks as $tk): ?>
          <tr>
            <td><?= View::e(View::local($tk, 'title')) ?></td>
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
  <h3><?= t('user.dashboard.recent_estimates') ?></h3>
  <?php if (empty($recentEstimates)): ?>
    <p class="help-text"><?= t('user.dashboard.no_estimates_yet') ?> <a href="/app/estimates/new"><?= t('user.dashboard.create_one') ?></a>.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead>
      <tbody>
      <?php foreach ($recentEstimates as $e): ?>
        <tr>
          <td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e(View::local($e, 'title')) ?></a></td>
          <td><span class="badge badge-gray"><?= View::e($e['status']) ?></span></td>
          <td><?= View::money((float)$e['total']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
