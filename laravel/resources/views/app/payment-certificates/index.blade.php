@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.payment_certificates.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/payment-certificates/create" class="btn btn-primary"><?= t('user.payment_certificates.new') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.boq.contract_value') ?></div><div class="value"><?= money($contractValue) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.payment_certificates.cumulative') ?></div><div class="value"><?= money($cumulativeCertified) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.retention_held') ?></div><div class="value"><?= money($retentionHeld) ?></div></div>
</div>

<?php if (empty($certificates)): ?>
  <div class="empty-state card">
    <div class="icon">📄</div>
    <p><?= t('user.payment_certificates.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('user.payment_certificates.number') ?></th>
          <th><?= t('user.payment_certificates.date') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('user.payment_certificates.gross') ?></th>
          <th><?= t('user.payment_certificates.retention') ?></th>
          <th><?= t('user.payment_certificates.advance_recovery') ?></th>
          <th><?= t('user.payment_certificates.net_payable') ?></th>
          <th><?= t('user.payment_certificates.cumulative') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($certificates as $cert): ?>
        <tr>
          <td><a href="/app/payment-certificates/<?= $cert['id'] ?>">#<?= $cert['certificate_number'] ?></a></td>
          <td><?= e($cert['certificate_date']) ?></td>
          <td><span class="badge <?= $cert['status'] === 'certified' ? 'badge-green' : 'badge-gray' ?>"><?= e($cert['status']) ?></span></td>
          <td><?= money((float)$cert['gross_amount']) ?></td>
          <td><?= money((float)$cert['retention_amount']) ?></td>
          <td><?= money((float)$cert['advance_recovery_amount']) ?></td>
          <td><?= money((float)$cert['net_payable']) ?></td>
          <td><?= money((float)$cert['cumulative_certified']) ?></td>
          <td><a href="/app/payment-certificates/<?= $cert['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

@endsection
