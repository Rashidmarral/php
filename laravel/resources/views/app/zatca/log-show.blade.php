@extends('layouts.app')

@section('content')
<?php
$numberField = ['invoice' => 'invoice_number', 'credit_note' => 'note_number', 'debit_note' => 'note_number'][$type];
$fkColumn = ['invoice' => 'invoice_id', 'credit_note' => 'credit_note_id', 'debit_note' => 'debit_note_id'][$type];
$typeLabel = t('user.zatca.type_' . $type);
$reference = $document?->{$numberField} ?? '#' . $log->{$fkColumn};
$statusClasses = ['cleared' => 'green', 'reported' => 'green', 'failed' => 'red', 'queued' => 'yellow'];
$statusLabels = ['cleared' => t('user.zatca.status_cleared'), 'reported' => t('user.zatca.status_reported'), 'failed' => t('user.zatca.status_failed'), 'queued' => t('user.zatca.status_queued')];
?>
<div class="page-head" style="align-items:flex-start;">
  <div>
    <h1><?= e($typeLabel) ?> <?= e($reference) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e(strtoupper($log->invoice_type) . ' (' . ucfirst($log->direction) . ')') ?></p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <span class="badge badge-<?= $statusClasses[$log->status] ?? 'gray' ?>"><?= e($statusLabels[$log->status] ?? $log->status) ?></span>
    <a href="/app/zatca" class="btn btn-secondary">← <?= t('user.zatca.back_to_dashboard') ?></a>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div class="card">
    <h3 style="margin-bottom:12px;"><?= t('user.zatca.submission_details') ?></h3>
    <dl style="margin:0;">
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('user.zatca.uuid') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e($log->request_uuid) ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('user.zatca.invoice_hash') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e(\Illuminate\Support\Str::limit((string) $log->invoice_hash, 40)) ?: '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('user.zatca.previous_invoice_hash') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e(\Illuminate\Support\Str::limit((string) $log->previous_invoice_hash, 40)) ?: '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('user.zatca.submission_date') ?></dt><dd><?= $log->submitted_at ? e($log->submitted_at->format('Y-m-d H:i')) : '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('user.zatca.cleared_at') ?></dt><dd><?= $log->cleared_at ? e($log->cleared_at->format('Y-m-d H:i')) : '—' ?></dd></div>
    </dl>
    <?php if ($log->error_message): ?>
      <div class="alert alert-error" style="margin-top:14px;"><strong><?= t('user.zatca.error_message') ?>:</strong> <?= e($log->error_message) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin-bottom:12px;"><?= t('user.zatca.response') ?></h3>
    <?php if ($log->response_payload): ?>
      <textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px;"><?= e($log->response_payload) ?></textarea>
    <?php else: ?>
      <p class="help-text"><?= t('user.zatca.no_response_recorded') ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-bottom:12px;"><?= t('user.zatca.xml_payload') ?></h3>
  <?php if ($log->xml_payload): ?>
    <textarea readonly rows="14" style="width:100%;font-family:monospace;font-size:12px;"><?= e($log->xml_payload) ?></textarea>
  <?php else: ?>
    <p class="help-text"><?= t('user.zatca.no_xml_recorded') ?></p>
  <?php endif; ?>
</div>
@endsection
