@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('debit_note.select_invoice_title') ?></h1>
  <a href="/app/debit-notes" class="btn btn-light"><?= t('credit_note.back_to_notes') ?></a>
</div>

<?php if (empty($invoices)): ?>
  <div class="card empty-state">
    <div class="icon">➕</div>
    <h3><?= t('user.invoices.no_invoices_title') ?></h3>
    <p><?= t('user.invoices.no_invoices_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('user.invoices.invoice_number') ?></th><th><?= t('common.client') ?></th><th><?= t('common.total') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($invoices as $i): ?>
      <tr>
        <td><?= e($i['invoice_number']) ?></td>
        <td><?= e($i['client_name'] ? local($i, 'client_name') : '—') ?></td>
        <td><?= money((float)$i['total']) ?></td>
        <td><a href="/app/debit-notes/create?invoice_id=<?= $i['id'] ?>" class="btn btn-sm btn-primary"><?= t('debit_note.new') ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
