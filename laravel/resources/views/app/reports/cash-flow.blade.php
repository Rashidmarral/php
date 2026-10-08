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
  <a href="/app/reports/cash-flow" class="active"><?= t('user.reports.tab_cash_flow') ?></a>
  <a href="/app/reports/accounting-export"><?= t('user.reports.tab_accounting_export') ?></a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;"><?= t('user.reports.cash_flow_hint') ?></p>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.reports.overdue_in') ?></div><div class="value"><?= money($totals['overdueIn']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.overdue_out') ?></div><div class="value"><?= money($totals['overdueOut']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.net_forecast') ?></div><div class="value"><?= money($totals['netForecast']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.projected_balance') ?></div><div class="value"><?= money($totals['endingBalance']) ?></div></div>
</div>

<div class="card">
  <h3 style="margin-bottom:14px;"><?= t('user.reports.cash_flow_chart_title') ?></h3>
  <div class="chart-box" style="height:300px;"><canvas id="chart-cash-flow"></canvas></div>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.reports.cash_flow_table_title') ?></h3>
  <table class="data">
    <thead>
      <tr>
        <th><?= t('user.reports.month_col') ?></th>
        <th><?= t('user.reports.projected_in') ?></th>
        <th><?= t('user.reports.projected_out') ?></th>
        <th><?= t('user.reports.net_col') ?></th>
        <th><?= t('user.reports.running_balance') ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td>
          <?php if ($row['key'] === 'overdue'): ?>
            <span class="badge badge-red"><?= t('user.reports.cash_flow_overdue') ?></span>
          <?php else: ?>
            <?= e(date('M Y', strtotime($row['key'] . '-01'))) ?>
          <?php endif; ?>
        </td>
        <td><?= money($row['in']) ?></td>
        <td><?= money($row['out']) ?></td>
        <td style="color:<?= $row['net'] < 0 ? 'var(--danger)' : 'var(--brand)' ?>;"><?= money($row['net']) ?></td>
        <td style="color:<?= $row['cumulative'] < 0 ? 'var(--danger)' : 'inherit' ?>;"><?= money($row['cumulative']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;
  var css = getComputedStyle(document.documentElement);
  var brand = css.getPropertyValue('--brand').trim() || '#1f8a5f';
  var danger = css.getPropertyValue('--danger').trim() || '#dc2626';
  var muted = css.getPropertyValue('--muted').trim() || '#64708a';
  var border = css.getPropertyValue('--border').trim() || '#e2e5ee';
  Chart.defaults.font.family = "'Cairo', sans-serif";
  Chart.defaults.color = muted;

  var rows = @json($rows);
  var overdueLabel = <?= json_encode(t('user.reports.cash_flow_overdue')) ?>;
  var labels = rows.map(function (r) {
    return r.key === 'overdue' ? overdueLabel : r.key;
  });

  var canvas = document.getElementById('chart-cash-flow');
  if (canvas) {
    new Chart(canvas, {
      data: {
        labels: labels,
        datasets: [
          {
            type: 'bar',
            label: <?= json_encode(t('user.reports.projected_in')) ?>,
            data: rows.map(function (r) { return r.in; }),
            backgroundColor: brand,
            borderRadius: 4,
            maxBarThickness: 36,
            order: 2,
          },
          {
            type: 'bar',
            label: <?= json_encode(t('user.reports.projected_out')) ?>,
            data: rows.map(function (r) { return -r.out; }),
            backgroundColor: danger,
            borderRadius: 4,
            maxBarThickness: 36,
            order: 2,
          },
          {
            type: 'line',
            label: <?= json_encode(t('user.reports.running_balance')) ?>,
            data: rows.map(function (r) { return r.cumulative; }),
            borderColor: muted,
            backgroundColor: muted,
            tension: 0.3,
            pointRadius: 4,
            pointBackgroundColor: muted,
            fill: false,
            order: 1,
          },
        ],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } },
        scales: {
          y: { grid: { color: border }, ticks: { callback: function (v) { return (Math.abs(v) >= 1000 ? (v / 1000) + 'k' : v); } } },
          x: { grid: { display: false } },
        },
      },
    });
  }
})();
</script>
@endpush
