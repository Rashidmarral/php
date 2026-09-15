@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/payment-certificates">&larr; <?= t('user.payment_certificates.title') ?></a></p>
    <h1><?= t('user.payment_certificates.number') ?> #<?= $certificate['certificate_number'] ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e(local($project, 'name')) ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span class="badge <?= $certificate['status'] === 'certified' ? 'badge-green' : 'badge-gray' ?>" style="font-size:13px;padding:6px 14px;"><?= e($certificate['status']) ?></span>
    <a href="/app/payment-certificates/<?= $certificate['id'] ?>/pdf" class="btn btn-outline" target="_blank"><?= t('user.payment_certificates.pdf') ?></a>
    <?php if ($certificate['status'] === 'draft'): ?>
      <a href="/app/payment-certificates/<?= $certificate['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
      <form method="post" action="/app/payment-certificates/<?= $certificate['id'] ?>/certify" onsubmit="return confirm('<?= t('user.payment_certificates.certify_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary"><?= t('user.payment_certificates.certify') ?></button>
      </form>
      <?php if ($isLatestDraft): ?>
        <form method="post" action="/app/payment-certificates/<?= $certificate['id'] ?>/delete" onsubmit="return confirm('<?= t('user.payment_certificates.delete_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($invoice): ?>
  <div class="alert" style="max-width:820px;background:#e6f4f1;color:#0a4d42;border:1px solid #b7ded4;margin-bottom:20px;">
    <?= t('user.payment_certificates.certified', ['number' => $certificate['certificate_number'], 'invoice' => $invoice['invoice_number']]) ?>
    <a href="/app/invoices/<?= $invoice['id'] ?>" style="margin-inline-start:8px;"><?= t('user.payment_certificates.view_invoice') ?></a>
  </div>
<?php endif; ?>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.payment_certificates.gross') ?></div><div class="value" style="font-size:18px;"><?= money((float)$certificate['gross_amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.payment_certificates.retention') ?> (<?= e((string)$certificate['retention_percent']) ?>%)</div><div class="value" style="font-size:18px;"><?= money((float)$certificate['retention_amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.payment_certificates.advance_recovery') ?></div><div class="value" style="font-size:18px;"><?= money((float)$certificate['advance_recovery_amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.payment_certificates.net_payable') ?></div><div class="value" style="font-size:18px;color:var(--brand-dark);"><?= money((float)$certificate['net_payable']) ?></div></div>
</div>

<div class="card">
  <div style="overflow-x:auto;">
  <table class="data">
    <thead>
      <tr>
        <th><?= t('common.description_en') ?></th>
        <th><?= t('user.boq.uom') ?></th>
        <th><?= t('user.boq.qty') ?></th>
        <th><?= t('user.boq.unit_price') ?></th>
        <th><?= t('user.payment_certificates.previous_cumulative') ?></th>
        <th><?= t('user.payment_certificates.this_cumulative') ?></th>
        <th><?= t('user.payment_certificates.this_period_qty') ?></th>
        <th><?= t('user.payment_certificates.this_period_value') ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($lines as $line): ?>
      <tr>
        <td><?= e($line['description']) ?></td>
        <td><?= e($line['uom']) ?></td>
        <td class="num"><?= number_format((float)$line['contract_qty'], 2) ?></td>
        <td class="num"><?= number_format((float)$line['contract_unit_price'], 2) ?></td>
        <td class="num"><?= number_format((float)$line['previous_cumulative_qty'], 2) ?></td>
        <td class="num"><?= number_format((float)$line['cumulative_qty'], 2) ?></td>
        <td class="num"><?= number_format((float)$line['this_period_qty'], 2) ?></td>
        <td class="num"><?= money((float)$line['this_period_value']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if (!empty($certificate['notes'])): ?>
  <div class="card" style="margin-top:20px;">
    <h3 style="font-size:14px;"><?= t('user.payment_certificates.notes') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($certificate['notes']) ?></p>
  </div>
<?php endif; ?>

@endsection
