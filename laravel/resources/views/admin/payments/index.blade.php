@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.payments.title') ?></h1>
  <a href="/admin/payments/export.csv" class="btn btn-light">⬇ <?= t('common.export_csv') ?></a>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(2,220px);">
  <div class="kpi"><div class="label"><?= t('admin.payments.total_collected') ?></div><div class="value"><?= money($total) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.payments.pending_approval') ?></div><div class="value"><?= $pendingCount ?></div></div>
</div>

<?php if (empty($payments)): ?>
  <div class="card empty-state"><div class="icon">💵</div><h3><?= t('admin.payments.none_yet') ?></h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.date') ?></th><th><?= t('common.company') ?></th><th><?= t('common.reference') ?></th><th><?= t('common.method') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= e($p['created_at']) ?></td>
        <td><a href="/admin/companies/<?= $p['company_id'] ?>"><?= e($p['company_name']) ?></a></td>
        <td><?= e($p['reference']) ?></td>
        <td><?= e(strtoupper($p['method'])) ?></td>
        <td><?= money((float)$p['amount']) ?></td>
        <td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':($p['status']==='refunded'?'blue':'red')) ?>"><?= e($p['status']) ?></span></td>
        <td style="display:flex;gap:6px;">
          <?php if ($p['status'] === 'pending'): ?>
            <form method="post" action="/admin/payments/<?= $p['id'] ?>/approve" onsubmit="return confirm('<?= t('admin.payments.approve_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-primary"><?= t('common.approve') ?></button>
            </form>
            <form method="post" action="/admin/payments/<?= $p['id'] ?>/reject" onsubmit="return confirm('<?= t('admin.payments.reject_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-light"><?= t('common.reject') ?></button>
            </form>
          <?php endif; ?>
          <a href="/admin/payments/<?= $p['id'] ?>" class="btn btn-sm btn-outline"><?= t('admin.payments.manage') ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  @include('admin.partials.pagination', ['paginator' => $payments])
<?php endif; ?>

@endsection
