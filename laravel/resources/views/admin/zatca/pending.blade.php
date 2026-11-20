@extends('layouts.admin')

@section('content')
<?php $pendingTotal = $pendingInvoices->count() + $pendingCreditNotes->count() + $pendingDebitNotes->count(); ?>
<div class="page-head">
  <div>
    <h1><?= t('admin.zatca_logs.pending_title') ?> — <?= e($company->name) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('admin.zatca_logs.pending_hint') ?></p>
  </div>
  <a href="/admin/zatca" class="btn btn-secondary"><?= t('admin.zatca_logs.back_to_overview') ?></a>
</div>

<?php if (!$company->isZatcaOnboarded()): ?>
  <div class="card empty-state"><div class="icon">⚠️</div><h3><?= t('admin.zatca_logs.not_onboarded_yet') ?></h3></div>
<?php elseif ($pendingTotal === 0): ?>
  <div class="card empty-state"><div class="icon">✅</div><h3><?= t('admin.zatca_logs.nothing_pending') ?></h3></div>
<?php else: ?>
  <table class="data">
    <thead>
      <tr>
        <th><?= t('admin.zatca_logs.document') ?></th>
        <th><?= t('common.type') ?></th>
        <th><?= t('common.client') ?></th>
        <th><?= t('common.status') ?></th>
        <th style="text-align:right;"><?= t('common.total') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pendingInvoices as $doc): ?>
        <tr>
          <td><a href="/app/invoices/<?= $doc->id ?>"><?= e($doc->invoice_number) ?></a></td>
          <td><?= t('admin.zatca_logs.type_invoice') ?></td>
          <td class="help-text"><?= e($doc->client?->name ?? '—') ?></td>
          <td><span class="badge badge-<?= $doc->zatca_status === 'failed' ? 'red' : 'gray' ?>"><?= e($doc->zatca_status) ?></span></td>
          <td style="text-align:right;"><?= money((float) $doc->total) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($pendingCreditNotes as $doc): ?>
        <tr>
          <td><a href="/app/credit-notes/<?= $doc->id ?>"><?= e($doc->note_number) ?></a></td>
          <td><?= t('admin.zatca_logs.type_credit_note') ?></td>
          <td class="help-text"><?= e($doc->client?->name ?? '—') ?></td>
          <td><span class="badge badge-<?= $doc->zatca_status === 'failed' ? 'red' : 'gray' ?>"><?= e($doc->zatca_status) ?></span></td>
          <td style="text-align:right;"><?= money((float) $doc->total) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php foreach ($pendingDebitNotes as $doc): ?>
        <tr>
          <td><a href="/app/debit-notes/<?= $doc->id ?>"><?= e($doc->note_number) ?></a></td>
          <td><?= t('admin.zatca_logs.type_debit_note') ?></td>
          <td class="help-text"><?= e($doc->client?->name ?? '—') ?></td>
          <td><span class="badge badge-<?= $doc->zatca_status === 'failed' ? 'red' : 'gray' ?>"><?= e($doc->zatca_status) ?></span></td>
          <td style="text-align:right;"><?= money((float) $doc->total) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
@endsection
