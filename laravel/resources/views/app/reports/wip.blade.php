@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit"><?= t('user.reports.tab_cost_variance') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
  <a href="/app/reports/cash-flow"><?= t('user.reports.tab_cash_flow') ?></a>
  <a href="/app/reports/wip" class="active"><?= t('user.reports.tab_wip') ?></a>
  <a href="/app/reports/cost-codes"><?= t('user.reports.tab_cost_codes') ?></a>
  <a href="/app/reports/accounting-export"><?= t('user.reports.tab_accounting_export') ?></a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;"><?= t('user.reports.wip_hint') ?></p>

<?php if (empty($rows)): ?>
  <div class="card empty-state"><div class="icon">📊</div><h3><?= t('user.reports.no_projects_title') ?></h3></div>
<?php else: ?>

  <div class="card" style="margin-bottom:24px;">
    <h3><?= t('user.reports.wip_portfolio_summary') ?></h3>
    <div class="kpi-grid" style="grid-template-columns:repeat(5,1fr);margin-top:12px;">
      <div class="kpi"><div class="label"><?= t('user.reports.contract_value') ?></div><div class="value" style="font-size:19px;"><?= money($totals['contractValue']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.wip_earned_revenue') ?></div><div class="value" style="font-size:19px;"><?= money($totals['earnedRevenue']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.wip_billed_to_date') ?></div><div class="value" style="font-size:19px;"><?= money($totals['billedToDate']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.wip_over_under_billed') ?></div><div class="value" style="font-size:19px;color:<?= $totals['overUnderBilled'] > 0 ? 'var(--warning)' : 'var(--success)' ?>;"><?= money($totals['overUnderBilled']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.reports.wip_cost_to_complete') ?></div><div class="value" style="font-size:19px;"><?= money($totals['costToComplete']) ?></div></div>
    </div>
    <p class="help-text" style="margin-top:12px;margin-bottom:0;">
      <?php if ($alertCount > 0): ?>
        <span style="color:var(--danger);font-weight:600;">⚠️ <?= t('user.reports.wip_projects_with_alerts', ['count' => $alertCount]) ?></span>
      <?php else: ?>
        <?= t('user.reports.wip_no_projects_with_alerts') ?>
      <?php endif; ?>
    </p>
  </div>

  <div class="card">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('common.project') ?></th>
          <th><?= t('user.reports.contract_value') ?></th>
          <th><?= t('user.reports.wip_percent_complete') ?></th>
          <th><?= t('user.reports.wip_earned_revenue') ?></th>
          <th><?= t('user.reports.wip_billed_to_date') ?></th>
          <th><?= t('user.reports.wip_over_under_billed') ?></th>
          <th><?= t('user.reports.wip_cost_to_complete') ?></th>
          <th><?= t('user.reports.wip_projected_profit') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): $p = $r['project']; ?>
        <tr>
          <td><a href="/app/projects/<?= $p['id'] ?>"><?= e($p['name']) ?></a></td>
          <td><?= money($r['contractValue']) ?></td>
          <td style="color:<?= $r['percentComplete'] > 100 ? 'var(--danger)' : 'inherit' ?>;"><?= number_format($r['percentComplete'], 1) ?>%</td>
          <td><?= money($r['earnedRevenue']) ?></td>
          <td><?= money($r['billedToDate']) ?></td>
          <td style="color:<?= $r['overUnderBilled'] > 0 ? 'var(--warning)' : 'var(--success)' ?>;font-weight:600;"><?= ($r['overUnderBilled'] > 0 ? '+' : '') . money($r['overUnderBilled']) ?></td>
          <td><?= money($r['costToComplete']) ?></td>
          <td style="color:<?= $r['projectedFinalProfit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;"><?= money($r['projectedFinalProfit']) ?></td>
          <td>
            <?php if ($r['alert']): ?>
              <span class="badge badge-red"><?= t('user.reports.wip_alert_badge') ?></span>
            <?php else: ?>
              <span class="badge badge-green"><?= t('user.reports.on_track_badge') ?></span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:600;">
          <td><?= t('common.total') ?></td>
          <td><?= money($totals['contractValue']) ?></td>
          <td></td>
          <td><?= money($totals['earnedRevenue']) ?></td>
          <td><?= money($totals['billedToDate']) ?></td>
          <td style="color:<?= $totals['overUnderBilled'] > 0 ? 'var(--warning)' : 'var(--success)' ?>;"><?= ($totals['overUnderBilled'] > 0 ? '+' : '') . money($totals['overUnderBilled']) ?></td>
          <td><?= money($totals['costToComplete']) ?></td>
          <td></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>

<?php endif; ?>

@endsection
