@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.recurring_invoices.title') ?></h1>
  <a href="/app/recurring-invoices/create" class="btn btn-primary"><?= t('user.recurring_invoices.new') ?></a>
</div>

<?php if (empty($templates)): ?>
  <div class="card empty-state">
    <div class="icon">🔁</div>
    <h3><?= t('user.recurring_invoices.no_templates_title') ?></h3>
    <p><?= t('user.recurring_invoices.no_templates_hint') ?></p>
    <a href="/app/recurring-invoices/create" class="btn btn-primary"><?= t('user.recurring_invoices.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.client') ?></th><th><?= t('user.recurring_invoices.frequency') ?></th><th><?= t('user.recurring_invoices.next_run_date') ?></th><th><?= t('user.recurring_invoices.generated_count') ?></th><th><?= t('common.status') ?></th></tr></thead>
    <tbody>
    <?php foreach ($templates as $rt): ?>
      <tr>
        <td><a href="/app/recurring-invoices/<?= $rt['id'] ?>"><?= e($rt['title']) ?></a></td>
        <td><?= e($rt['client_name'] ? local($rt, 'client_name') : '—') ?></td>
        <td><?= e(\App\Models\RecurringInvoice::FREQUENCIES[$rt['frequency']] ?? $rt['frequency']) ?></td>
        <td class="help-text"><?= e($rt['next_run_date']) ?></td>
        <td><?= (int) $rt['generated_count'] ?></td>
        <td><span class="badge badge-<?= $rt['is_active'] ? 'green' : 'gray' ?>"><?= $rt['is_active'] ? t('user.recurring_invoices.status_active') : t('user.recurring_invoices.status_paused') ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
