@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/subcontracts">&larr; <?= t('user.subcontracts.title') ?></a></p>
    <h1><?= e($subcontract['title']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e(local($project, 'name')) ?> · <?= e($supplier['name'] ?? '—') ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span class="badge <?= $subcontract['status'] === 'active' ? 'badge-green' : ($subcontract['status'] === 'terminated' ? 'badge-red' : 'badge-gray') ?>" style="font-size:13px;padding:6px 14px;"><?= e(\App\Models\Subcontract::STATUSES[$subcontract['status']] ?? ucfirst($subcontract['status'])) ?></span>
    <?php if (!$hasCertifiedPayment): ?>
      <a href="/app/subcontracts/<?= $subcontract['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <?php endif; ?>
    <?php if (!$hasAnyPayment): ?>
      <form method="post" action="/app/subcontracts/<?= $subcontract['id'] ?>/delete" onsubmit="return confirm('<?= t('user.subcontracts.delete_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
      </form>
    <?php endif; ?>
    <a href="/app/subcontracts/<?= $subcontract['id'] ?>/payments/create" class="btn btn-primary"><?= t('user.subcontract_payments.new') ?></a>
  </div>
</div>

<?php if ($hasCertifiedPayment): ?>
  <p class="help-text" style="max-width:820px;margin-top:-14px;margin-bottom:16px;"><?= t('user.subcontracts.locked_hint') ?></p>
<?php endif; ?>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.subcontracts.contract_value') ?></div><div class="value" style="font-size:18px;"><?= money((float)$subcontract['contract_value']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontracts.cumulative_paid') ?></div><div class="value" style="font-size:18px;"><?= money($cumulativePaid) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontracts.remaining') ?></div><div class="value" style="font-size:18px;"><?= money($remaining) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontracts.retention_held') ?></div><div class="value" style="font-size:18px;"><?= money($retentionHeld) ?></div></div>
</div>

<?php if (!empty($subcontract['description'])): ?>
  <div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:14px;"><?= t('user.subcontracts.description') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($subcontract['description']) ?></p>
  </div>
<?php endif; ?>

<div class="card">
  <h3 style="font-size:14px;"><?= t('user.subcontract_payments.title') ?></h3>
  <?php if (empty($payments)): ?>
    <p class="help-text"><?= t('user.subcontract_payments.none_yet') ?></p>
  <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('user.subcontract_payments.number') ?></th>
          <th><?= t('user.subcontract_payments.date') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('user.subcontract_payments.previous_cumulative') ?></th>
          <th><?= t('user.subcontract_payments.cumulative_value') ?></th>
          <th><?= t('user.subcontract_payments.this_period_value') ?></th>
          <th><?= t('user.subcontract_payments.retention') ?></th>
          <th><?= t('user.subcontract_payments.net_payable') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><a href="/app/subcontract-payments/<?= $p['id'] ?>">#<?= $p['payment_number'] ?></a></td>
          <td><?= e($p['payment_date']) ?></td>
          <td><span class="badge <?= $p['status'] === 'certified' ? 'badge-green' : 'badge-gray' ?>"><?= e($p['status']) ?></span></td>
          <td class="num"><?= number_format((float)$p['previous_cumulative_value'], 2) ?></td>
          <td class="num"><?= number_format((float)$p['cumulative_value'], 2) ?></td>
          <td class="num"><?= money((float)$p['this_period_value']) ?></td>
          <td class="num"><?= money((float)$p['retention_amount']) ?></td>
          <td class="num"><?= money((float)$p['net_payable']) ?></td>
          <td><a href="/app/subcontract-payments/<?= $p['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

@endsection
