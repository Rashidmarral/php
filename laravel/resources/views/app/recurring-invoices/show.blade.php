@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($template['title']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?><?php if ($project): ?> · <?= t('common.project') ?>: <a href="/app/projects/<?= $project['id'] ?>"><?= e(local($project, 'name')) ?></a><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= $template['is_active'] ? 'green' : 'gray' ?>" style="font-size:13px;padding:6px 14px;"><?= $template['is_active'] ? t('user.recurring_invoices.status_active') : t('user.recurring_invoices.status_paused') ?></span>
    <a href="/app/recurring-invoices/<?= $template['id'] ?>/edit" class="btn btn-outline"><?= t('common.edit') ?></a>
    <form method="post" action="/app/recurring-invoices/<?= $template['id'] ?>/toggle-active">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-light"><?= $template['is_active'] ? t('user.recurring_invoices.pause') : t('user.recurring_invoices.resume') ?></button>
    </form>
    <form method="post" action="/app/recurring-invoices/<?= $template['id'] ?>/delete" onsubmit="return confirm('<?= t('user.recurring_invoices.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?></th><th><?= t('user.invoices.line_total') ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): $lineTotal = (float)$it['qty'] * (float)$it['unit_price']; ?>
        <tr><td><?= e(local($it, 'description')) ?></td><td><?= e($it['qty']) ?></td><td><?= money((float)$it['unit_price']) ?></td><td><?= money($lineTotal) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p class="help-text" style="margin-top:14px;">
    <?= t('user.recurring_invoices.frequency') ?>: <strong><?= e($frequencies[$template['frequency']] ?? $template['frequency']) ?></strong> ·
    <?= t('user.recurring_invoices.next_run_date') ?>: <strong><?= e($template['next_run_date']) ?></strong> ·
    <?= t('user.recurring_invoices.due_days') ?>: <strong><?= (int) $template['due_days'] ?></strong>
    <?php if ($template['last_generated_at']): ?> · <?= t('user.recurring_invoices.last_generated') ?>: <strong><?= e($template['last_generated_at']) ?></strong><?php endif; ?>
  </p>
  <?php if ((float) $template['retention_percent'] > 0): ?>
    <p class="help-text"><?= t('user.invoices.retention_withheld_percent') ?>: <strong><?= e((string)$template['retention_percent']) ?>%</strong></p>
  <?php endif; ?>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('user.recurring_invoices.generated_invoices') ?></h3>
  <?php if (empty($generated)): ?>
    <p class="help-text"><?= t('user.recurring_invoices.no_generated_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>#</th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th><th><?= t('common.due') ?></th></tr></thead>
      <tbody>
      <?php foreach ($generated as $inv): ?>
        <tr>
          <td><a href="/app/invoices/<?= $inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td>
          <td><span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$inv['status']] ?? 'yellow' ?>"><?= e($inv['status']) ?></span></td>
          <td><?= money((float)$inv['total']) ?></td>
          <td class="help-text"><?= e($inv['due_date']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
