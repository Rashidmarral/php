@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('credit_note.title') ?></h1>
  <a href="/app/credit-notes/create" class="btn btn-primary"><?= t('credit_note.new') ?></a>
</div>

<?php if (empty($creditNotes)): ?>
  <div class="card empty-state">
    <div class="icon">↩️</div>
    <h3><?= t('credit_note.no_notes_title') ?></h3>
    <p><?= t('credit_note.no_notes_hint') ?></p>
    <a href="/app/credit-notes/create" class="btn btn-primary"><?= t('credit_note.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>#</th><th><?= t('credit_note.invoice') ?></th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
    <?php foreach ($creditNotes as $n): ?>
      <tr>
        <td><a href="/app/credit-notes/<?= $n['id'] ?>"><?= e($n['note_number']) ?></a></td>
        <td><?= e($n['invoice_number'] ?? '—') ?></td>
        <td><?= e($n['client_name'] ? local($n, 'client_name') : '—') ?></td>
        <td><span class="badge badge-<?= $n['status'] === 'void' ? 'red' : 'green' ?>"><?= e($n['status']) ?></span></td>
        <td><?= money((float)$n['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
