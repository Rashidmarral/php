@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.reports.title') ?></h1>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('admin.reports.total_companies') ?></div><div class="value"><?= $totalCompanies ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.reports.active') ?></div><div class="value"><?= $statusMap['active'] ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.reports.trial') ?></div><div class="value"><?= $statusMap['trial'] ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.reports.suspended_cancelled') ?></div><div class="value"><?= $statusMap['suspended'] + $statusMap['cancelled'] ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('admin.reports.revenue_chart') ?></h3>
    <?php if (array_sum($monthly) == 0): ?>
      <p class="help-text"><?= t('admin.reports.no_transactions') ?></p>
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
    <h3><?= t('admin.reports.signups_chart') ?></h3>
    <?php if (array_sum($signupsMonthly) == 0): ?>
      <p class="help-text"><?= t('admin.reports.no_signups') ?></p>
    <?php else: ?>
      <div style="display:flex;align-items:end;gap:10px;height:160px;padding-top:10px;">
        <?php foreach ($signupsMonthly as $month => $count): ?>
          <div style="flex:1;text-align:center;">
            <div style="background:var(--accent);border-radius:4px 4px 0 0;height:<?= max(4, round($count / $maxSignups * 130)) ?>px;" title="<?= $count ?> signups"></div>
            <div class="help-text" style="margin-top:6px;"><?= e(date('M', strtotime($month . '-01'))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-2" style="margin-top:20px;">
  <div class="card">
    <h3><?= t('admin.reports.plan_distribution') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('admin.reports.plan_col') ?></th><th><?= t('admin.reports.companies_col') ?></th></tr></thead>
      <tbody>
        <?php foreach ($planCounts as $p): ?>
          <tr><td><?= e($p['name']) ?></td><td><?= (int) $p['company_count'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="card">
    <h3><?= t('admin.reports.transaction_status') ?></h3>
    <?php $txStatusLabels = ['paid' => t('admin.payment.status_paid'), 'pending' => t('admin.payment.status_pending'), 'failed' => t('admin.payment.status_failed'), 'refunded' => t('admin.payment.status_refunded')]; ?>
    <table class="data">
      <thead><tr><th><?= t('common.status') ?></th><th><?= t('admin.reports.count_col') ?></th><th><?= t('common.total') ?></th></tr></thead>
      <tbody>
        <?php foreach ($paymentMap as $status => $row): ?>
          <tr>
            <td><span class="badge badge-<?= $status==='paid'?'green':($status==='pending'?'yellow':($status==='refunded'?'blue':'red')) ?>"><?= $txStatusLabels[$status] ?? ucfirst($status) ?></span></td>
            <td><?= $row['c'] ?></td>
            <td><?= money($row['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><?= t('admin.reports.transactions_total', ['count' => $totalTransactions]) ?></p>
  </div>
</div>

<div class="grid grid-2" style="margin-top:20px;">
  <div class="card">
    <h3><?= t('admin.reports.zatca_funnel') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('admin.reports.stage_col') ?></th><th><?= t('admin.reports.companies_col') ?></th></tr></thead>
      <tbody>
        <tr><td><?= t('admin.reports.zatca_not_started') ?></td><td><?= $zatcaMap['not_started'] ?></td></tr>
        <tr><td><?= t('admin.reports.zatca_csr_generated') ?></td><td><?= $zatcaMap['csr_generated'] ?></td></tr>
        <tr><td><?= t('admin.reports.zatca_compliance_issued') ?></td><td><?= $zatcaMap['compliance_pending'] + $zatcaMap['compliance_verified'] ?></td></tr>
        <tr><td><?= t('admin.reports.zatca_live') ?></td><td><span class="badge badge-green"><?= $zatcaMap['onboarded'] ?></span></td></tr>
        <tr><td><?= t('admin.reports.zatca_error') ?></td><td><span class="badge badge-red"><?= $zatcaMap['error'] ?></span></td></tr>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><a href="/admin/companies"><?= t('admin.reports.manage_zatca_link') ?></a></p>
  </div>

  <div class="card">
    <h3><?= t('admin.reports.leads_funnel') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('common.status') ?></th><th><?= t('admin.reports.count_col') ?></th></tr></thead>
      <tbody>
        <tr><td><?= t('admin.reports.lead_new') ?></td><td><span class="badge badge-yellow"><?= $leadMap['new'] ?></span></td></tr>
        <tr><td><?= t('admin.reports.lead_contacted') ?></td><td><?= $leadMap['contacted'] ?></td></tr>
        <tr><td><?= t('admin.reports.lead_converted') ?></td><td><span class="badge badge-green"><?= $leadMap['converted'] ?></span></td></tr>
        <tr><td><?= t('admin.reports.lead_dismissed') ?></td><td><?= $leadMap['dismissed'] ?></td></tr>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:8px;"><a href="/admin/quick-estimate/leads"><?= t('admin.reports.view_all_leads_link') ?></a></p>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3><?= t('admin.reports.top_companies') ?></h3>
  <?php if (empty($topCompanies) || (float)($topCompanies[0]['total_paid'] ?? 0) == 0): ?>
    <p class="help-text"><?= t('admin.reports.no_transactions') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.company') ?></th><th><?= t('admin.reports.total_paid_col') ?></th></tr></thead>
      <tbody>
        <?php foreach ($topCompanies as $c): ?>
          <?php if ((float) $c['total_paid'] <= 0) continue; ?>
          <tr><td><a href="/admin/companies/<?= $c['id'] ?>"><?= e($c['name']) ?></a></td><td><?= money((float)$c['total_paid']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
