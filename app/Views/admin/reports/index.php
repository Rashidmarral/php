<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Reports</h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Total companies</div><div class="value"><?= $totalCompanies ?></div></div>
  <div class="kpi"><div class="label">Active</div><div class="value"><?= $statusMap['active'] ?></div></div>
  <div class="kpi"><div class="label">Trial</div><div class="value"><?= $statusMap['trial'] ?></div></div>
  <div class="kpi"><div class="label">Suspended / Cancelled</div><div class="value"><?= $statusMap['suspended'] + $statusMap['cancelled'] ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Revenue collected — last 6 months</h3>
    <?php if (array_sum($monthly) == 0): ?>
      <p class="help-text">No paid transactions yet.</p>
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
    <h3>New company signups — last 6 months</h3>
    <?php if (array_sum($signupsMonthly) == 0): ?>
      <p class="help-text">No signups yet.</p>
    <?php else: ?>
      <div style="display:flex;align-items:end;gap:10px;height:160px;padding-top:10px;">
        <?php foreach ($signupsMonthly as $month => $count): ?>
          <div style="flex:1;text-align:center;">
            <div style="background:var(--accent);border-radius:4px 4px 0 0;height:<?= max(4, round($count / $maxSignups * 130)) ?>px;" title="<?= $count ?> signups"></div>
            <div class="help-text" style="margin-top:6px;"><?= View::e(date('M', strtotime($month . '-01'))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2" style="margin-top:20px;">
  <div class="card">
    <h3>Plan distribution</h3>
    <table class="data">
      <thead><tr><th>Plan</th><th>Companies</th></tr></thead>
      <tbody>
        <?php foreach ($planCounts as $p): ?>
          <tr><td><?= View::e($p['name']) ?></td><td><?= (int) $p['company_count'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3>Transaction status</h3>
    <table class="data">
      <thead><tr><th>Status</th><th>Count</th><th>Total</th></tr></thead>
      <tbody>
        <?php foreach ($paymentMap as $status => $row): ?>
          <tr>
            <td><span class="badge badge-<?= $status==='paid'?'green':($status==='pending'?'yellow':($status==='refunded'?'blue':'red')) ?>"><?= ucfirst($status) ?></span></td>
            <td><?= $row['c'] ?></td>
            <td><?= View::money($row['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><?= $totalTransactions ?> transactions total.</p>
  </div>
</div>

<div class="grid grid-2" style="margin-top:20px;">
  <div class="card">
    <h3>ZATCA onboarding funnel</h3>
    <table class="data">
      <thead><tr><th>Stage</th><th>Companies</th></tr></thead>
      <tbody>
        <tr><td>Not started</td><td><?= $zatcaMap['not_started'] ?></td></tr>
        <tr><td>CSR generated</td><td><?= $zatcaMap['csr_generated'] ?></td></tr>
        <tr><td>Compliance CSID issued</td><td><?= $zatcaMap['compliance_csid'] ?></td></tr>
        <tr><td>Live (production)</td><td><span class="badge badge-green"><?= $zatcaMap['active'] ?></span></td></tr>
        <tr><td>Error</td><td><span class="badge badge-red"><?= $zatcaMap['error'] ?></span></td></tr>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><a href="/admin/companies">Manage a company's ZATCA onboarding →</a></p>
  </div>

  <div class="card">
    <h3>Quick Estimate leads funnel (public site)</h3>
    <table class="data">
      <thead><tr><th>Status</th><th>Count</th></tr></thead>
      <tbody>
        <tr><td>New</td><td><span class="badge badge-yellow"><?= $leadMap['new'] ?></span></td></tr>
        <tr><td>Contacted</td><td><?= $leadMap['contacted'] ?></td></tr>
        <tr><td>Converted</td><td><span class="badge badge-green"><?= $leadMap['converted'] ?></span></td></tr>
        <tr><td>Dismissed</td><td><?= $leadMap['dismissed'] ?></td></tr>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><a href="/admin/quick-estimate/leads">View all leads →</a></p>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3>Top companies by revenue</h3>
  <?php if (empty($topCompanies) || (float)($topCompanies[0]['total_paid'] ?? 0) == 0): ?>
    <p class="help-text">No paid transactions yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Company</th><th>Total paid</th></tr></thead>
      <tbody>
        <?php foreach ($topCompanies as $c): ?>
          <?php if ((float) $c['total_paid'] <= 0) continue; ?>
          <tr><td><a href="/admin/companies/<?= $c['id'] ?>"><?= View::e($c['name']) ?></a></td><td><?= View::money((float)$c['total_paid']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
