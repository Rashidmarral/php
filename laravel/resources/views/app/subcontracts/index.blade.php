@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.subcontracts.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/subcontracts/create" class="btn btn-primary"><?= t('user.subcontracts.new') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.subcontracts.contract_value') ?></div><div class="value"><?= money($totalContractValue) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontracts.cumulative_paid') ?></div><div class="value"><?= money($totalCumulativePaid) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.subcontracts.retention_held') ?></div><div class="value"><?= money($totalRetentionHeld) ?></div></div>
</div>

<?php if (empty($subcontracts)): ?>
  <div class="empty-state card">
    <div class="icon">🧱</div>
    <p><?= t('user.subcontracts.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('common.title') ?></th>
          <th><?= t('user.subcontracts.subcontractor') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('user.subcontracts.contract_value') ?></th>
          <th><?= t('user.subcontracts.cumulative_paid') ?></th>
          <th><?= t('user.subcontracts.retention_held') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($subcontracts as $s): ?>
        <tr>
          <td><a href="/app/subcontracts/<?= $s['id'] ?>"><?= e($s['title']) ?></a></td>
          <td><?= e($s['supplier_name']) ?></td>
          <td><span class="badge <?= $s['status'] === 'active' ? 'badge-green' : ($s['status'] === 'terminated' ? 'badge-red' : 'badge-gray') ?>"><?= e($statuses[$s['status']] ?? ucfirst($s['status'])) ?></span></td>
          <td><?= money((float)$s['contract_value']) ?></td>
          <td><?= money((float)$s['cumulative_paid']) ?></td>
          <td><?= money((float)$s['retention_held']) ?></td>
          <td><a href="/app/subcontracts/<?= $s['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

@endsection
