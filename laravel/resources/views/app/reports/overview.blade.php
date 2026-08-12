@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports" class="active"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit"><?= t('user.reports.tab_profit') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.reports.active_projects') ?></div><div class="value"><?= $activeProjects ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.total_budget') ?></div><div class="value"><?= money($totalBudget) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.revenue_collected') ?></div><div class="value"><?= money($totalRevenuePaid) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.outstanding') ?></div><div class="value"><?= money($totalOutstanding) ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('user.reports.revenue_chart') ?></h3>
    <?php if (array_sum($monthly) == 0): ?>
      <p class="help-text"><?= t('user.reports.no_paid_invoices') ?></p>
    <?php else: ?>
      <div style="display:flex;align-items:end;gap:10px;height:160px;padding-top:10px;">
        <?php foreach ($monthly as $month => $amount): ?>
          <div style="flex:1;text-align:center;">
            <div style="background:var(--brand);border-radius:4px 4px 0 0;height:<?= max(4, round($amount / $maxMonthly * 130)) ?>px;" title="<?= e(number_format($amount,2)) ?> SAR"></div>
            <div class="help-text" style="margin-top:6px;"><?= e(date('M', strtotime($month . '-01'))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3><?= t('user.reports.win_rate_title') ?></h3>
    <?php if ($winRate === null): ?>
      <p class="help-text"><?= t('user.reports.win_rate_hint') ?></p>
    <?php else: ?>
      <div class="kpi" style="text-align:center;margin-bottom:14px;">
        <div class="label"><?= t('user.reports.win_rate') ?></div>
        <div class="value" style="font-size:32px;"><?= $winRate ?>%</div>
      </div>
      <p class="help-text" style="text-align:center;"><?= t('user.reports.accepted_declined', ['accepted' => $accepted, 'declined' => $declined]) ?></p>
    <?php endif; ?>
  </div>
</div>

@endsection
