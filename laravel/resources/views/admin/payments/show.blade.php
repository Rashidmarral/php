@extends('layouts.admin')

@section('content')
<div class="page-head">
  <div>
    <h1><?= t('admin.payment.transaction', ['id' => $payment['id']]) ?></h1>
    <p class="help-text" style="margin-top:4px;"><a href="/admin/companies/<?= $payment['company_id'] ?>"><?= e($payment['company_name']) ?></a> · <?= e($payment['created_at']) ?></p>
  </div>
  <a href="/admin/payments" class="btn btn-secondary">← <?= t('admin.payment.back_to_payments') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi"><div class="label"><?= t('common.amount') ?></div><div class="value" style="font-size:16px;"><?= money((float)$payment['amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('common.method') ?></div><div class="value" style="font-size:16px;"><?= e(strtoupper($payment['method'])) ?></div></div>
  <div class="kpi"><div class="label"><?= t('common.status') ?></div><div class="value" style="font-size:16px;"><span class="badge badge-<?= $payment['status']==='paid'?'green':($payment['status']==='pending'?'yellow':($payment['status']==='refunded'?'blue':'red')) ?>"><?= e($payment['status']) ?></span></div></div>
  <div class="kpi"><div class="label"><?= t('admin.payment.reviewed') ?></div><div class="value" style="font-size:16px;"><?= e($payment['reviewed_at'] ?: '—') ?></div></div>
</div>

<?php if (!empty($payment['proof_file_path'])): ?>
  <div class="card" style="margin-bottom:24px;max-width:520px;">
    <h3><?= t('admin.payment.bank_proof') ?></h3>
    <a href="<?= e($payment['proof_file_path']) ?>" target="_blank" rel="noopener" class="btn btn-outline"><?= t('admin.payment.view_uploaded_proof') ?></a>
  </div>
<?php endif; ?>

<?php if ($payment['status'] === 'pending'): ?>
  <div class="card" style="margin-bottom:24px;max-width:520px;">
    <h3><?= t('admin.payment.quick_decision') ?></h3>
    <p class="help-text"><?= t('admin.payment.quick_decision_hint') ?></p>
    <div style="display:flex;gap:8px;">
      <form method="post" action="/admin/payments/<?= $payment['id'] ?>/approve" onsubmit="return confirm('<?= t('admin.payments.approve_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-primary"><?= t('common.approve') ?></button>
      </form>
      <form method="post" action="/admin/payments/<?= $payment['id'] ?>/reject" onsubmit="return confirm('<?= t('admin.payments.reject_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-light"><?= t('common.reject') ?></button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.payment.edit_transaction') ?></h3>
  <p class="help-text"><?= t('admin.payment.edit_transaction_hint') ?></p>
  <form method="post" action="/admin/payments/<?= $payment['id'] ?>/update">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.amount') ?> (SAR)</label><input type="number" step="0.01" name="amount" value="<?= e((string)$payment['amount']) ?>"></div>
      <div class="form-group">
        <label><?= t('common.status') ?></label>
        <select name="status">
          <?php foreach (['paid'=>t('admin.payment.status_paid'),'pending'=>t('admin.payment.status_pending'),'failed'=>t('admin.payment.status_failed'),'refunded'=>t('admin.payment.status_refunded')] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= $payment['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.method') ?></label><input type="text" name="method" value="<?= e($payment['method']) ?>"></div>
      <div class="form-group"><label><?= t('common.reference') ?></label><input type="text" name="reference" value="<?= e($payment['reference']) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('admin.payment.save_transaction') ?></button>
  </form>
</div>

<div class="card" style="max-width:520px;">
  <h3><?= t('admin.payment.reassign_package') ?></h3>
  <p class="help-text"><?= t('admin.payment.reassign_hint') ?></p>
  <form method="post" action="/admin/payments/<?= $payment['id'] ?>/apply-plan" onsubmit="return confirm('<?= t('admin.payment.apply_confirm') ?>');" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <label><?= t('common.plan') ?></label>
      <select name="plan_id">
        <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($payment['plan_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('admin.company.cycle') ?></label>
      <select name="billing_cycle">
        <option value="monthly" <?= ($payment['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>><?= t('billing.monthly') ?></option>
        <option value="yearly" <?= ($payment['billing_cycle'] ?? '') === 'yearly' ? 'selected' : '' ?>><?= t('billing.yearly') ?></option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('admin.payment.apply_to_company') ?></button>
  </form>
</div>

@endsection
