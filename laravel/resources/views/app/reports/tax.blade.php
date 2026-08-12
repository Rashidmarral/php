@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit"><?= t('user.reports.tab_profit') ?></a>
  <a href="/app/reports/tax" class="active"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="kpi"><div class="label"><?= t('user.reports.invoices_with_vat') ?></div><div class="value"><?= $invoiceCount ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.taxable_amount') ?></div><div class="value"><?= money($totalTaxable) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.vat_collected') ?></div><div class="value"><?= money($totalVat) ?></div></div>
</div>

<?php if (empty($byMonth)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <h3><?= t('user.reports.no_vat_invoices_title') ?></h3>
    <p><?= t('user.reports.no_vat_invoices_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('user.reports.month_col') ?></th><th><?= t('user.reports.invoices_col') ?></th><th><?= t('user.reports.taxable_amount') ?></th><th><?= t('user.reports.vat_collected') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
    <?php foreach ($byMonth as $month => $data): ?>
      <tr>
        <td><?= e(date('F Y', strtotime($month . '-01'))) ?></td>
        <td><?= $data['count'] ?></td>
        <td><?= money($data['subtotal']) ?></td>
        <td><?= money($data['vat']) ?></td>
        <td><?= money($data['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
