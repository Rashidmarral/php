@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/subcontracts/<?= $subcontract['id'] ?>">&larr; <?= e($subcontract['title']) ?></a></p>
    <h1><?= t('user.subcontract_payments.number') ?> #<?= $payment['payment_number'] ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e(local($project, 'name')) ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span class="badge <?= $payment['status'] === 'certified' ? 'badge-green' : 'badge-gray' ?>" style="font-size:13px;padding:6px 14px;"><?= e($payment['status']) ?></span>
    <?php if ($payment['status'] === 'draft'): ?>
      <a href="/app/subcontract-payments/<?= $payment['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
      <form method="post" action="/app/subcontract-payments/<?= $payment['id'] ?>/certify" onsubmit="return confirm('<?= t('user.subcontract_payments.certify_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary"><?= t('user.subcontract_payments.certify') ?></button>
      </form>
      <?php if ($isLatestDraft): ?>
        <form method="post" action="/app/subcontract-payments/<?= $payment['id'] ?>/delete" onsubmit="return confirm('<?= t('user.subcontract_payments.delete_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<?php if ($vendorBill): ?>
  <div class="alert" style="max-width:820px;background:#e6f4f1;color:#0a4d42;border:1px solid #b7ded4;margin-bottom:20px;">
    <?= t('user.subcontract_payments.certified', ['number' => $payment['payment_number'], 'bill' => $vendorBill['reference']]) ?>
  </div>
<?php endif; ?>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.subcontract_payments.previous_cumulative') ?></div><div class="value" style="font-size:18px;"><?= money((float)$payment['previous_cumulative_value']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontract_payments.cumulative_value') ?></div><div class="value" style="font-size:18px;"><?= money((float)$payment['cumulative_value']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontract_payments.this_period_value') ?></div><div class="value" style="font-size:18px;"><?= money((float)$payment['this_period_value']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontract_payments.retention') ?> (<?= e((string)$payment['retention_percent']) ?>%)</div><div class="value" style="font-size:18px;"><?= money((float)$payment['retention_amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontract_payments.net_payable') ?></div><div class="value" style="font-size:18px;color:var(--brand-dark);"><?= money((float)$payment['net_payable']) ?></div></div>
</div>

<?php if (!empty($payment['notes'])): ?>
  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.subcontract_payments.notes') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($payment['notes']) ?></p>
  </div>
<?php endif; ?>

@endsection
