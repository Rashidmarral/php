<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Business Reports</h1>
</div>

<div class="tabs">
  <a href="/app/reports" class="active">Performance</a>
  <a href="/app/reports/profit">Profit Tracker</a>
  <a href="/app/reports/tax">Tax Summary</a>
  <a href="/app/reports/retention">Retention Ledger</a>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Active Projects</div><div class="value"><?= $activeProjects ?></div></div>
  <div class="kpi"><div class="label">Total Budget</div><div class="value"><?= View::money($totalBudget) ?></div></div>
  <div class="kpi"><div class="label">Revenue Collected</div><div class="value"><?= View::money($totalRevenuePaid) ?></div></div>
  <div class="kpi"><div class="label">Outstanding</div><div class="value"><?= View::money($totalOutstanding) ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Revenue — last 6 months</h3>
    <?php if (array_sum($monthly) == 0): ?>
      <p class="help-text">No paid invoices yet.</p>
    <?php else: ?>
      <div style="display:flex;align-items:end;gap:10px;height:160px;padding-top:10px;">
        <?php foreach ($monthly as $month => $amount): ?>
          <div style="flex:1;text-align:center;">
            <div style="background:var(--brand);border-radius:4px 4px 0 0;height:<?= max(4, round($amount / $maxMonthly * 130)) ?>px;" title="<?= View::e(number_format($amount,2)) ?> SAR"></div>
            <div class="help-text" style="margin-top:6px;"><?= View::e(date('M', strtotime($month . '-01'))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Estimate win rate</h3>
    <?php if ($winRate === null): ?>
      <p class="help-text">Not enough decided estimates yet (accepted or declined).</p>
    <?php else: ?>
      <div class="kpi" style="text-align:center;margin-bottom:14px;">
        <div class="label">Win rate</div>
        <div class="value" style="font-size:32px;"><?= $winRate ?>%</div>
      </div>
      <p class="help-text" style="text-align:center;"><?= $accepted ?> accepted · <?= $declined ?> declined</p>
    <?php endif; ?>
  </div>
</div>
