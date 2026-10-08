<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= t('admin.dashboard.title') ?></h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('admin.dashboard.mrr') ?></div><div class="value"><?= View::money($mrr) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.dashboard.revenue_month') ?></div><div class="value"><?= View::money($revenueThisMonth) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.dashboard.total_companies') ?></div><div class="value"><?= $totalCompanies ?></div><div class="delta">+<?= $signupsThisMonth ?> <?= t('admin.dashboard.this_month') ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.dashboard.active_trial') ?></div><div class="value"><?= $activeCompanies ?> / <?= $trialCompanies ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('admin.dashboard.recent_companies') ?></h3>
    <?php if (empty($recentCompanies)): ?>
      <p class="help-text"><?= t('admin.dashboard.no_companies') ?></p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th><?= t('common.company') ?></th><th><?= t('common.plan') ?></th><th><?= t('common.status') ?></th></tr></thead>
        <tbody>
        <?php foreach ($recentCompanies as $c): ?>
          <tr>
            <td><a href="/admin/companies/<?= $c['id'] ?>"><?= View::e($c['name']) ?></a></td>
            <td><?= View::e($c['plan_name'] ?? '—') ?></td>
            <td><span class="badge badge-<?= $c['status']==='active'?'green':($c['status']==='suspended'?'red':'yellow') ?>"><?= View::e($c['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3><?= t('admin.dashboard.by_plan') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('common.plan') ?></th><th><?= t('admin.dashboard.companies') ?></th></tr></thead>
      <tbody>
      <?php foreach ($planCounts as $pc): ?>
        <tr><td><?= View::e($pc['name']) ?></td><td><?= (int)$pc['company_count'] ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
