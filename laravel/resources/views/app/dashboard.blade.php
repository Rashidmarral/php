@extends('layouts.app')

@section('content')
<?php if ($trialDaysLeft !== null): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:<?= $trialDaysLeft <= 3 ? '#fdf3e0' : 'var(--brand-light)' ?>;border-color:<?= $trialDaysLeft <= 3 ? '#e8c76b' : 'var(--brand)' ?>;">
    <div>
      <strong><?= $trialDaysLeft > 0 ? t($trialDaysLeft === 1 ? 'user.dashboard.trial_ends_singular' : 'user.dashboard.trial_ends_plural', ['days' => $trialDaysLeft]) : t('user.dashboard.trial_ended') ?></strong>
      <?php if ($currentPlan): ?><span style="color:var(--muted);"> <?= t('user.dashboard.currently_on_plan', ['plan' => e($currentPlan['name'])]) ?></span><?php endif; ?>
    </div>
    <a href="/app/billing" class="btn btn-primary btn-sm"><?= t('user.dashboard.subscribe_now') ?></a>
  </div>
<?php endif; ?>
<?php if (!empty($expiringDocs)): ?>
  <div class="card" style="margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;background:#fdf3e0;border-color:#e8c76b;">
    <div>
      <strong><?= t(count($expiringDocs) === 1 ? 'user.dashboard.docs_expiring_singular' : 'user.dashboard.docs_expiring_plural', ['count' => count($expiringDocs)]) ?></strong>
      <span style="color:var(--muted);"> <?= e(implode(', ', array_column(array_slice($expiringDocs, 0, 3), 'name'))) ?><?= count($expiringDocs) > 3 ? '…' : '' ?></span>
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
  <div class="kpi"><div class="label"><?= t('user.dashboard.total_budget') ?></div><div class="value"><?= money($totalBudget) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.dashboard.outstanding') ?></div><div class="value"><?= money($outstanding) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.dashboard.paid_this_month') ?></div><div class="value"><?= money($paidThisMonth) ?></div></div>
  <?php if ($openSafetyIncidents !== null): ?>
    <div class="kpi"><div class="label"><?= t('user.dashboard.open_safety_incidents') ?></div><div class="value"><?= $openSafetyIncidents ?></div></div>
  <?php endif; ?>
</div>

<div class="grid grid-3" style="margin-bottom:24px;align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;"><?= t('user.dashboard.revenue_trend_title') ?></h3>
    <div class="chart-box"><canvas id="chart-revenue-trend"></canvas></div>
  </div>
  <div class="card">
    <h3 style="margin-bottom:14px;"><?= t('user.dashboard.budget_overview_title') ?></h3>
    <div class="chart-box"><canvas id="chart-budget-overview"></canvas></div>
  </div>
  <div class="card">
    <h3 style="margin-bottom:14px;"><?= t('user.dashboard.project_status_title') ?></h3>
    <?php if (!empty($recentProjects) || $activeProjects > 0): ?>
      <div class="chart-box"><canvas id="chart-project-status"></canvas></div>
    <?php else: ?>
      <p class="help-text"><?= t('user.dashboard.no_projects_yet') ?></p>
    <?php endif; ?>
  </div>
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
            <td><a href="/app/projects/<?= $p['id'] ?>"><?= e(local($p, 'name')) ?></a></td>
            <td><span class="badge badge-blue"><?= e(str_replace('_',' ',$p['status'])) ?></span></td>
            <td><?= money((float)$p['budget']) ?></td>
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
            <td><?= e(local($tk, 'title')) ?></td>
            <td><?= e($tk['start_date']) ?></td>
            <td><span class="badge badge-<?= $tk['status'] === 'in_progress' ? 'yellow' : 'gray' ?>"><?= e(str_replace('_',' ',$tk['status'])) ?></span></td>
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
          <td><a href="/app/estimates/<?= $e['id'] ?>"><?= e(local($e, 'title')) ?></a></td>
          <td><span class="badge badge-gray"><?= e($e['status']) ?></span></td>
          <td><?= money((float)$e['total']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;
  var css = getComputedStyle(document.documentElement);
  var brand = css.getPropertyValue('--brand').trim() || '#1f8a5f';
  var accent = css.getPropertyValue('--accent').trim() || '#e0a526';
  var danger = css.getPropertyValue('--danger').trim() || '#dc2626';
  var muted = css.getPropertyValue('--muted').trim() || '#64708a';
  var border = css.getPropertyValue('--border').trim() || '#e2e5ee';
  Chart.defaults.font.family = "'Cairo', sans-serif";
  Chart.defaults.color = muted;

  var revenueTrend = @json($revenueTrend);
  var revenueCanvas = document.getElementById('chart-revenue-trend');
  if (revenueCanvas) {
    new Chart(revenueCanvas, {
      type: 'line',
      data: {
        labels: revenueTrend.map(function (r) { return r.label; }),
        datasets: [{
          data: revenueTrend.map(function (r) { return r.total; }),
          borderColor: brand,
          backgroundColor: brand + '22',
          fill: true,
          tension: 0.35,
          pointRadius: 3,
          pointBackgroundColor: brand,
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: border }, ticks: { callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  var budgetCanvas = document.getElementById('chart-budget-overview');
  if (budgetCanvas) {
    new Chart(budgetCanvas, {
      type: 'bar',
      data: {
        labels: [<?= json_encode(t('user.dashboard.total_budget')) ?>, <?= json_encode(t('user.dashboard.paid_this_month')) ?>, <?= json_encode(t('user.dashboard.outstanding')) ?>],
        datasets: [{
          data: [<?= (float) $totalBudget ?>, <?= (float) $paidThisMonth ?>, <?= (float) $outstanding ?>],
          backgroundColor: [brand, accent, danger],
          borderRadius: 6,
          maxBarThickness: 46,
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: border }, ticks: { callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  var statusCanvas = document.getElementById('chart-project-status');
  if (statusCanvas) {
    var statusCounts = @json($projectStatusCounts);
    var statusColors = { planning: '#2563eb', in_progress: accent, on_hold: muted, completed: brand, cancelled: danger };
    var keys = Object.keys(statusCounts);
    new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels: keys.map(function (k) { return k.replace('_', ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); }),
        datasets: [{
          data: keys.map(function (k) { return statusCounts[k]; }),
          backgroundColor: keys.map(function (k) { return statusColors[k] || accent; }),
          borderWidth: 0,
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } },
      },
    });
  }
})();
</script>
@endpush
