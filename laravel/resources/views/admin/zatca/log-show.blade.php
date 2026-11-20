@extends('layouts.admin')

@section('content')
<?php
$numberField = ['invoice' => 'invoice_number', 'credit_note' => 'note_number', 'debit_note' => 'note_number'][$type];
$fkColumn = ['invoice' => 'invoice_id', 'credit_note' => 'credit_note_id', 'debit_note' => 'debit_note_id'][$type];
$typeLabel = t('admin.zatca_logs.type_' . $type);
$reference = $document?->{$numberField} ?? '#' . $log->{$fkColumn};
$statusClasses = ['cleared' => 'green', 'reported' => 'green', 'failed' => 'red', 'queued' => 'yellow'];
$statusLabels = ['cleared' => t('admin.zatca_logs.status_cleared'), 'reported' => t('admin.zatca_logs.status_reported'), 'failed' => t('admin.zatca_logs.status_failed'), 'queued' => t('admin.zatca_logs.status_queued')];
$envLabels = ['developer' => t('admin.zatca.env_developer'), 'simulation' => t('admin.zatca.env_simulation'), 'production' => t('admin.zatca.env_production')];
$alreadyLocked = $document?->isZatcaLocked() ?? false;
?>
<div class="page-head" style="align-items:flex-start;">
  <div>
    <h1><?= e($typeLabel) ?> <?= e($reference) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?= e($log->company?->name ?? t('admin.zatca_logs.unknown_company')) ?>
      · <?= e($envLabels[$log->environment] ?? ucfirst((string) $log->environment)) ?>
      · <?= e(strtoupper($log->invoice_type) . ' (' . ucfirst($log->direction) . ')') ?>
    </p>
  </div>
  <div style="display:flex;align-items:center;gap:10px;">
    <span class="badge badge-<?= $statusClasses[$log->status] ?? 'gray' ?>"><?= e($statusLabels[$log->status] ?? $log->status) ?></span>
    <a href="/admin/zatca/logs" class="btn btn-secondary">← <?= t('admin.zatca_logs.back_to_logs') ?></a>
  </div>
</div>

<?php if (!$alreadyLocked && $document): ?>
  <form method="post" action="/admin/zatca/logs/<?= $type ?>/<?= $log->id ?>/retry" onsubmit="return confirm('<?= t('admin.zatca_logs.retry_confirm') ?>');" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary"><?= t('admin.zatca_logs.retry_submission') ?></button>
  </form>
<?php elseif ($alreadyLocked): ?>
  <p class="help-text" style="margin-bottom:20px;color:var(--success);"><?= t('admin.zatca_logs.already_cleared_hint') ?></p>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div class="card">
    <h3 style="margin-bottom:12px;"><?= t('admin.zatca_logs.submission_details') ?></h3>
    <dl style="margin:0;">
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('admin.zatca_logs.uuid') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e($log->request_uuid) ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('admin.zatca_logs.invoice_hash') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e(\Illuminate\Support\Str::limit((string) $log->invoice_hash, 40)) ?: '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('admin.zatca_logs.previous_invoice_hash') ?></dt><dd style="font-family:monospace;font-size:12px;text-align:right;word-break:break-all;"><?= e(\Illuminate\Support\Str::limit((string) $log->previous_invoice_hash, 40)) ?: '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('admin.zatca_logs.submission_date') ?></dt><dd><?= $log->submitted_at ? e($log->submitted_at->format('Y-m-d H:i')) : '—' ?></dd></div>
      <div style="display:flex;justify-content:space-between;gap:12px;padding:4px 0;"><dt class="help-text"><?= t('admin.zatca_logs.cleared_at') ?></dt><dd><?= $log->cleared_at ? e($log->cleared_at->format('Y-m-d H:i')) : '—' ?></dd></div>
    </dl>
    <?php if ($log->error_message): ?>
      <div class="alert alert-error" style="margin-top:14px;"><strong><?= t('admin.zatca_logs.error_message') ?>:</strong> <?= e($log->error_message) ?></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3 style="margin-bottom:12px;"><?= t('admin.zatca_logs.response') ?></h3>
    <?php if ($log->response_payload): ?>
      <textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px;"><?= e($log->response_payload) ?></textarea>
    <?php else: ?>
      <p class="help-text"><?= t('admin.zatca_logs.no_response_recorded') ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3 style="margin-bottom:12px;"><?= t('admin.zatca_logs.xml_payload') ?></h3>
  <?php if ($log->xml_payload): ?>
    <textarea readonly rows="14" style="width:100%;font-family:monospace;font-size:12px;"><?= e($log->xml_payload) ?></textarea>
  <?php else: ?>
    <p class="help-text"><?= t('admin.zatca_logs.no_xml_recorded') ?></p>
  <?php endif; ?>
</div>
@endsection
