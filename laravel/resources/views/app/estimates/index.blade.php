@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.estimates.title') ?></h1>
  <a href="/app/estimates/new" class="btn btn-primary"><?= t('user.estimates.new') ?></a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.estimates.total_estimates') ?></div><div class="value"><?= $stats['count'] ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.estimates.pipeline_value') ?></div><div class="value"><?= money($stats['pipelineValue']) ?></div></div>
  <div class="kpi">
    <div class="label"><?= t('user.reports.win_rate') ?></div>
    <?php if ($stats['winRate'] === null): ?>
      <div class="value" style="font-size:20px;color:var(--muted);"><?= t('user.reports.win_rate_hint') ?></div>
    <?php else: ?>
      <div class="value"><?= $stats['winRate'] ?>%</div>
    <?php endif; ?>
  </div>
</div>

<div class="toolbar" style="align-items:center;justify-content:space-between;">
  <div style="display:flex;gap:10px;flex-wrap:wrap;">
    <a href="/app/estimates<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-light' ?>"><?= t('common.all') ?> (<?= array_sum($counts) ?>)</a>
    <?php foreach ($statuses as $s): ?>
      <a href="/app/estimates?status=<?= $s ?><?= $q !== '' ? '&q=' . urlencode($q) : '' ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-light' ?>"><?= t('user.estimates.status_' . $s) ?> (<?= $counts[$s] ?>)</a>
    <?php endforeach; ?>
  </div>
  <form method="get" action="/app/estimates" style="display:flex;gap:8px;">
    <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="<?= t('user.estimates.search_placeholder') ?>" style="min-width:220px;">
    <button type="submit" class="btn btn-outline btn-sm"><?= t('common.search') ?></button>
  </form>
</div>

<?php if (empty($estimates)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <?php if ($statusFilter !== '' || $q !== ''): ?>
      <h3><?= t('common.no_results') ?></h3>
      <a href="/app/estimates" class="btn btn-outline"><?= t('common.all') ?></a>
    <?php else: ?>
      <h3><?= t('user.estimates.no_estimates_title') ?></h3>
      <p><?= t('user.estimates.no_estimates_hint') ?></p>
      <a href="/app/estimates/new" class="btn btn-primary"><?= t('user.estimates.new') ?></a>
    <?php endif; ?>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
    <?php foreach ($estimates as $e): ?>
      <tr>
        <td><a href="/app/estimates/<?= $e['id'] ?>"><?= e(local($e, 'title')) ?></a></td>
        <td><?= e($e['client_name'] ? local($e, 'client_name') : '—') ?></td>
        <td>
          <?php if ($e['isExpired']): ?>
            <span class="badge badge-red"><?= t('user.estimates.status_expired') ?></span>
          <?php else: ?>
            <span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$e['status']] ?? 'gray' ?>"><?= e($e['status']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= money((float)$e['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
