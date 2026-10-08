@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.invoices.title') ?></h1>
  <a href="/app/invoices/create" class="btn btn-primary"><?= t('user.invoices.new') ?></a>
</div>

<?php if (empty($invoices)): ?>
  <div class="card empty-state">
    <div class="icon">💳</div>
    <h3><?= t('user.invoices.no_invoices_title') ?></h3>
    <p><?= t('user.invoices.no_invoices_hint') ?></p>
    <a href="/app/invoices/create" class="btn btn-primary"><?= t('user.invoices.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>#</th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th><th><?= t('common.due') ?></th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $i): ?>
      <tr>
        <td><a href="/app/invoices/<?= $i['id'] ?>"><?= e($i['invoice_number']) ?></a></td>
        <td><?= e($i['client_name'] ? local($i, 'client_name') : '—') ?></td>
        <td><span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$i['status']] ?? 'yellow' ?>"><?= e($i['status']) ?></span></td>
        <td><?= money((float)$i['total']) ?></td>
        <td class="help-text"><?= e($i['due_date']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
