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
  <a href="/app/reports/wip"><?= t('user.reports.tab_wip') ?></a>
  <a href="/app/reports/cost-codes" class="active"><?= t('user.reports.tab_cost_codes') ?></a>
  <a href="/app/reports/accounting-export"><?= t('user.reports.tab_accounting_export') ?></a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;"><?= t('user.reports.cost_codes_hint') ?></p>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.reports.estimated_col') ?></div><div class="value"><?= money($totals['estimated']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.actual_col') ?></div><div class="value"><?= money($totals['actual']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.uncoded_estimated') ?></div><div class="value"><?= money($totals['uncodedEstimated']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.uncoded_actual') ?></div><div class="value"><?= money($totals['uncodedActual']) ?></div></div>
</div>

<div class="card">
  <h3 style="margin-bottom:14px;"><?= t('user.reports.cost_codes_table_title') ?></h3>

  <?php if (empty($tree)): ?>
    <p class="help-text"><?= t('user.reports.no_cost_codes_title') ?></p>
  <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('user.cost_codes.column') ?></th>
          <th><?= t('user.reports.estimated_col') ?></th>
          <th><?= t('user.reports.actual_col') ?></th>
          <th><?= t('user.reports.variance_col') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tree as $division): ?>
          <tr style="font-weight:700;background:var(--bg);">
            <td><?= e($division['code']) ?> — <?= e(local($division, 'description')) ?></td>
            <td><?= money($division['estimated']) ?></td>
            <td><?= money($division['actual']) ?></td>
            <td style="color:<?= ($division['estimated'] - $division['actual']) < 0 ? 'var(--danger)' : 'inherit' ?>;"><?= money($division['estimated'] - $division['actual']) ?></td>
          </tr>
          <?php foreach ($division['children'] as $child): ?>
            <tr>
              <td style="padding-inline-start:28px;"><?= e($child['code']) ?> — <?= e(local($child, 'description')) ?></td>
              <td><?= money($child['estimated']) ?></td>
              <td><?= money($child['actual']) ?></td>
              <td style="color:<?= ($child['estimated'] - $child['actual']) < 0 ? 'var(--danger)' : 'inherit' ?>;"><?= money($child['estimated'] - $child['actual']) ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        <tr style="font-weight:700;border-top:2px solid var(--border);">
          <td><?= t('user.reports.uncoded_bucket') ?></td>
          <td><?= money($totals['uncodedEstimated']) ?></td>
          <td><?= money($totals['uncodedActual']) ?></td>
          <td style="color:<?= ($totals['uncodedEstimated'] - $totals['uncodedActual']) < 0 ? 'var(--danger)' : 'inherit' ?>;"><?= money($totals['uncodedEstimated'] - $totals['uncodedActual']) ?></td>
        </tr>
      </tbody>
      <tfoot>
        <tr style="font-weight:700;">
          <td><?= t('user.reports.portfolio_summary') ?></td>
          <td><?= money($totals['estimated']) ?></td>
          <td><?= money($totals['actual']) ?></td>
          <td><?= money($totals['estimated'] - $totals['actual']) ?></td>
        </tr>
      </tfoot>
    </table>
    </div>
    <p class="help-text" style="margin-top:12px;"><?= t('user.reports.uncoded_bucket_hint') ?></p>
  <?php endif; ?>
</div>

@endsection
