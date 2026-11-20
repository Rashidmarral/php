@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.projects.title') ?> <?php if ($projectLimit !== null && $projectLimit < 999): ?><span class="badge badge-<?= $withinProjectLimit ? 'gray' : 'red' ?>"><?= count($projects) ?> / <?= $projectLimit ?></span><?php endif; ?></h1>
  <a href="/app/projects/create" class="btn btn-primary <?= $withinProjectLimit ? '' : 'disabled' ?>" <?= $withinProjectLimit ? '' : 'onclick="return false;" style="opacity:.5;cursor:not-allowed;"' ?>><?= t('user.projects.new') ?></a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.projects.active_projects') ?></div><div class="value"><?= $stats['activeCount'] ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.active_budget') ?></div><div class="value"><?= money($stats['activeBudget']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.purchase_orders.over_budget') ?></div><div class="value" style="<?= $stats['overBudgetCount'] > 0 ? 'color:var(--danger);' : '' ?>"><?= $stats['overBudgetCount'] ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.behind_schedule') ?></div><div class="value" style="<?= $stats['behindScheduleCount'] > 0 ? 'color:var(--danger);' : '' ?>"><?= $stats['behindScheduleCount'] ?></div></div>
</div>

<div class="toolbar" style="align-items:center;justify-content:space-between;">
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/app/projects<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-light' ?>"><?= t('common.all') ?> (<?= array_sum($counts) ?>)</a>
    <?php foreach ($statuses as $s): ?>
      <a href="/app/projects?status=<?= $s ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-light' ?>"><?= t('user.projects.status_' . $s) ?> (<?= $counts[$s] ?>)</a>
    <?php endforeach; ?>
  </div>
  <form method="get" action="/app/projects" style="display:flex;gap:8px;">
    <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= t('user.projects.search_placeholder') ?>" style="min-width:220px;">
    <button type="submit" class="btn btn-outline btn-sm"><?= t('common.search') ?></button>
  </form>
</div>

<?php if (empty($projects)): ?>
  <div class="card empty-state">
    <div class="icon">🏗️</div>
    <?php if ($statusFilter !== '' || $q !== ''): ?>
      <h3><?= t('common.no_results') ?></h3>
      <a href="/app/projects" class="btn btn-outline"><?= t('common.all') ?></a>
    <?php else: ?>
      <h3><?= t('user.projects.no_projects_title') ?></h3>
      <p><?= t('user.projects.no_projects_hint') ?></p>
      <a href="/app/projects/create" class="btn btn-primary"><?= t('user.projects.new') ?></a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.project') ?></th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.amount') ?></th><th><?= t('user.projects.dates_col') ?></th><th><?= t('user.projects.health_col') ?></th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
      <tr>
        <td><a href="/app/projects/<?= $p['id'] ?>"><?= e(local($p, 'name')) ?></a></td>
        <td><?= e($p['client_name'] ? local($p, 'client_name') : '—') ?></td>
        <td><span class="badge badge-blue"><?= e(str_replace('_',' ',$p['status'])) ?></span></td>
        <td><?= money((float)$p['budget']) ?></td>
        <td class="help-text"><?= e($p['start_date']) ?> → <?= e($p['end_date']) ?></td>
        <td>
          <?php if ($p['isOverBudget']): ?><span class="badge badge-red"><?= t('user.purchase_orders.over_budget') ?></span><?php endif; ?>
          <?php if ($p['isBehindSchedule']): ?><span class="badge badge-yellow"><?= t('user.projects.behind_schedule') ?></span><?php endif; ?>
          <?php if (!$p['isOverBudget'] && !$p['isBehindSchedule']): ?><span class="help-text">—</span><?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
