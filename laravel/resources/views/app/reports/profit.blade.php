@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit" class="active"><?= t('user.reports.tab_cost_variance') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;"><?= t('user.reports.cost_variance_hint') ?></p>

<?php if (empty($rows)): ?>
  <div class="card empty-state"><div class="icon">📊</div><h3><?= t('user.reports.no_projects_title') ?></h3></div>
<?php else: ?>

  <div class="card" style="margin-bottom:24px;">
    <h3><?= t('user.reports.portfolio_summary') ?></h3>
    <div class="kpi-grid" style="grid-template-columns:repeat(5,1fr);margin-top:12px;">
      <div class="kpi"><div class="label"><?= t('user.reports.contract_value') ?></div><div class="value" style="font-size:19px;"><?= money($totals['contractValue']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.estimated_cost') ?></div><div class="value" style="font-size:19px;"><?= money($totals['estimatedCost']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.actual_cost') ?></div><div class="value" style="font-size:19px;"><?= money($totals['actualCost']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.expected_profit') ?></div><div class="value" style="font-size:19px;color:<?= $totals['expectedProfit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= money($totals['expectedProfit']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.actual_profit') ?></div><div class="value" style="font-size:19px;color:<?= $totals['actualProfit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= money($totals['actualProfit']) ?></div></div>
    </div>
    <p class="help-text" style="margin-top:12px;margin-bottom:0;">
      <?php if ($alertCount > 0): ?>
        <span style="color:var(--danger);font-weight:600;">⚠️ <?= t('user.reports.projects_with_alerts', ['count' => $alertCount]) ?></span>
      <?php else: ?>
        <?= t('user.reports.no_projects_with_alerts') ?>
      <?php endif; ?>
    </p>
  </div>

  <?php foreach ($rows as $r): $p = $r['project']; ?>
  <div class="card" style="margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:4px;">
      <h3 style="margin:0;"><a href="/app/projects/<?= $p['id'] ?>"><?= e($p['name']) ?></a></h3>
      <?php if (!$r['hasBaseline']): ?>
        <span class="badge badge-gray"><?= t('user.reports.no_baseline_badge') ?></span>
      <?php elseif ($r['alert']): ?>
        <span class="badge badge-red"><?= t('user.reports.alert_badge') ?></span>
      <?php else: ?>
        <span class="badge badge-green"><?= t('user.reports.on_track_badge') ?></span>
      <?php endif; ?>
    </div>

    <?php if (!$r['hasBaseline']): ?>
      <p class="help-text"><?= t('user.reports.no_baseline_hint') ?></p>
    <?php else: ?>
      <div class="kpi-grid" style="grid-template-columns:repeat(5,1fr);margin-top:10px;">
        <div class="kpi"><div class="label"><?= t('user.reports.contract_value') ?></div><div class="value" style="font-size:18px;"><?= money($r['contractValue']) ?></div></div>
        <div class="kpi"><div class="label"><?= t('user.reports.estimated_cost') ?></div><div class="value" style="font-size:18px;"><?= money($r['estimatedCostTotal']) ?></div></div>
        <div class="kpi"><div class="label"><?= t('user.reports.actual_cost') ?></div><div class="value" style="font-size:18px;"><?= money($r['actualCostTotal']) ?></div></div>
        <div class="kpi"><div class="label"><?= t('user.reports.expected_profit') ?></div><div class="value" style="font-size:18px;color:<?= $r['expectedProfit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= money($r['expectedProfit']) ?></div></div>
        <div class="kpi"><div class="label"><?= t('user.reports.actual_profit') ?></div><div class="value" style="font-size:18px;color:<?= $r['actualProfit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= money($r['actualProfit']) ?></div></div>
      </div>

      <?php if ($r['alert']): ?>
        <div class="alert" style="background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;margin-top:14px;">
          <?= t('user.reports.erosion_alert', ['from' => money($r['expectedProfit']), 'to' => money($r['actualProfit'])]) ?>
        </div>
      <?php endif; ?>

      <h4 style="margin:18px 0 10px;font-size:14px;color:var(--muted);text-transform:uppercase;letter-spacing:.03em;"><?= t('user.reports.by_category') ?></h4>
      <table class="data">
        <thead>
          <tr>
            <th><?= t('common.category') ?></th>
            <th><?= t('user.reports.estimated_col') ?></th>
            <th><?= t('user.reports.actual_col') ?></th>
            <th><?= t('user.reports.variance_col') ?></th>
            <th style="width:220px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $key => $label):
          $est = $r['estimatedByCategory'][$key];
          $act = $r['actualByCategory'][$key];
          if ($est == 0.0 && $act == 0.0) continue;
          $variance = $act - $est;
          $barBase = max($est, $act, 1);
          $estWidth = min(100, round($est / $barBase * 100));
          $actWidth = min(100, round($act / $barBase * 100));
          $overBudget = $est > 0 && $act > $est;
        ?>
          <tr>
            <td><?= e($label) ?></td>
            <td><?= money($est) ?></td>
            <td><?= money($act) ?></td>
            <td style="color:<?= $variance > 0 ? 'var(--danger)' : 'var(--success)' ?>;font-weight:600;"><?= ($variance > 0 ? '+' : '') . number_format($variance, 2) ?></td>
            <td>
              <div style="background:var(--bg);border-radius:6px;height:8px;overflow:hidden;margin-bottom:3px;">
                <div style="background:var(--muted);opacity:.45;height:100%;width:<?= $estWidth ?>%;"></div>
              </div>
              <div style="background:var(--bg);border-radius:6px;height:8px;overflow:hidden;">
                <div style="background:<?= $overBudget ? 'var(--danger)' : 'var(--brand)' ?>;height:100%;width:<?= $actWidth ?>%;"></div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

<?php endif; ?>

@endsection
