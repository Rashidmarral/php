@extends('layouts.admin')

@section('content')
<?php
$statusClasses = ['cleared' => 'green', 'reported' => 'green', 'failed' => 'red', 'queued' => 'yellow'];
$statusLabels = ['cleared' => t('admin.zatca_logs.status_cleared'), 'reported' => t('admin.zatca_logs.status_reported'), 'failed' => t('admin.zatca_logs.status_failed'), 'queued' => t('admin.zatca_logs.status_queued')];
$envLabels = ['developer' => t('admin.zatca.env_developer'), 'simulation' => t('admin.zatca.env_simulation'), 'production' => t('admin.zatca.env_production')];
$renderRows = function ($logs, string $type, string $numberField, string $relation, string $fkColumn) use ($statusClasses, $statusLabels) {
    foreach ($logs as $log) {
        $document = $log->{$relation};
        $number = $document?->{$numberField} ?? '#' . $log->{$fkColumn};
        echo '<tr>';
        echo '<td>' . e($log->company?->name ?? '—') . '</td>';
        echo '<td>' . e($number) . '</td>';
        echo '<td class="help-text" style="font-family:monospace;font-size:12px;">' . e(\Illuminate\Support\Str::limit($log->request_uuid, 13)) . '</td>';
        echo '<td>' . e(strtoupper($log->invoice_type) . ' · ' . ucfirst($log->direction)) . '</td>';
        echo '<td class="help-text">' . ($log->submitted_at ? e($log->submitted_at->format('Y-m-d H:i')) : '—') . '</td>';
        echo '<td><span class="badge badge-' . ($statusClasses[$log->status] ?? 'gray') . '">' . e($statusLabels[$log->status] ?? $log->status) . '</span></td>';
        echo '<td>' . ($log->error_message ? '<span style="color:var(--danger);">' . e(\Illuminate\Support\Str::limit($log->error_message, 60)) . '</span>' : '<span class="help-text">—</span>') . '</td>';
        echo '<td><a href="/admin/zatca/logs/' . $type . '/' . $log->id . '">' . t('common.view') . '</a></td>';
        echo '</tr>';
    }
};
?>
<div class="page-head">
  <h1><?= t('admin.zatca_logs.logs_title') ?></h1>
  <a href="/admin/zatca" class="btn btn-secondary">← <?= t('admin.zatca_logs.back_to_overview') ?></a>
</div>

<form method="get" action="/admin/zatca/logs" class="card" style="margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label><?= t('common.company') ?></label>
    <select name="company_id">
      <option value=""><?= t('admin.zatca_logs.all_companies') ?></option>
      <?php foreach ($companies as $company): ?>
        <option value="<?= $company->id ?>" <?= (string) request('company_id') === (string) $company->id ? 'selected' : '' ?>><?= e($company->name) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.type') ?></label>
    <select name="type">
      <option value=""><?= t('admin.zatca_logs.all_document_types') ?></option>
      <option value="invoice" <?= $typeFilter === 'invoice' ? 'selected' : '' ?>><?= t('admin.zatca_logs.type_invoice') ?></option>
      <option value="credit_note" <?= $typeFilter === 'credit_note' ? 'selected' : '' ?>><?= t('admin.zatca_logs.type_credit_note') ?></option>
      <option value="debit_note" <?= $typeFilter === 'debit_note' ? 'selected' : '' ?>><?= t('admin.zatca_logs.type_debit_note') ?></option>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.status') ?></label>
    <select name="status">
      <option value=""><?= t('common.all') ?></option>
      <option value="queued" <?= request('status') === 'queued' ? 'selected' : '' ?>><?= t('admin.zatca_logs.status_queued') ?></option>
      <option value="cleared" <?= request('status') === 'cleared' ? 'selected' : '' ?>><?= t('admin.zatca_logs.status_cleared') ?></option>
      <option value="reported" <?= request('status') === 'reported' ? 'selected' : '' ?>><?= t('admin.zatca_logs.status_reported') ?></option>
      <option value="failed" <?= request('status') === 'failed' ? 'selected' : '' ?>><?= t('admin.zatca_logs.status_failed') ?></option>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.zatca.environment') ?></label>
    <select name="environment">
      <option value=""><?= t('admin.zatca_logs.all_environments') ?></option>
      <?php foreach (['developer', 'simulation', 'production'] as $env): ?>
        <option value="<?= $env ?>" <?= request('environment') === $env ? 'selected' : '' ?>><?= e($envLabels[$env]) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.filter') ?></button>
  <?php if (request()->anyFilled(['company_id', 'status', 'environment', 'type'])): ?>
    <a href="/admin/zatca/logs" class="btn btn-light"><?= t('common.clear') ?></a>
  <?php endif; ?>
</form>

<?php if ($invoiceLogs): ?>
  <h3 style="margin:20px 0 10px;"><?= t('admin.zatca_logs.type_invoice') ?></h3>
  <table class="data">
    <thead><tr><th><?= t('common.company') ?></th><th><?= t('admin.zatca_logs.document') ?></th><th><?= t('admin.zatca_logs.uuid') ?></th><th><?= t('admin.zatca_logs.invoice_type') ?></th><th><?= t('admin.zatca_logs.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('admin.zatca_logs.error_message') ?></th><th></th></tr></thead>
    <tbody>
      <?php if ($invoiceLogs->isEmpty()): ?>
        <tr><td colspan="8" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.zatca_logs.no_invoice_logs') ?></td></tr>
      <?php else: ?>
        <?php $renderRows($invoiceLogs, 'invoice', 'invoice_number', 'invoice', 'invoice_id'); ?>
      <?php endif; ?>
    </tbody>
  </table>
  @include('admin.partials.pagination', ['paginator' => $invoiceLogs])
<?php endif; ?>

<?php if ($creditNoteLogs): ?>
  <h3 style="margin:28px 0 10px;"><?= t('admin.zatca_logs.type_credit_note') ?></h3>
  <table class="data">
    <thead><tr><th><?= t('common.company') ?></th><th><?= t('admin.zatca_logs.document') ?></th><th><?= t('admin.zatca_logs.uuid') ?></th><th><?= t('admin.zatca_logs.invoice_type') ?></th><th><?= t('admin.zatca_logs.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('admin.zatca_logs.error_message') ?></th><th></th></tr></thead>
    <tbody>
      <?php if ($creditNoteLogs->isEmpty()): ?>
        <tr><td colspan="8" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.zatca_logs.no_credit_note_logs') ?></td></tr>
      <?php else: ?>
        <?php $renderRows($creditNoteLogs, 'credit_note', 'note_number', 'creditNote', 'credit_note_id'); ?>
      <?php endif; ?>
    </tbody>
  </table>
  @include('admin.partials.pagination', ['paginator' => $creditNoteLogs])
<?php endif; ?>

<?php if ($debitNoteLogs): ?>
  <h3 style="margin:28px 0 10px;"><?= t('admin.zatca_logs.type_debit_note') ?></h3>
  <table class="data">
    <thead><tr><th><?= t('common.company') ?></th><th><?= t('admin.zatca_logs.document') ?></th><th><?= t('admin.zatca_logs.uuid') ?></th><th><?= t('admin.zatca_logs.invoice_type') ?></th><th><?= t('admin.zatca_logs.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('admin.zatca_logs.error_message') ?></th><th></th></tr></thead>
    <tbody>
      <?php if ($debitNoteLogs->isEmpty()): ?>
        <tr><td colspan="8" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.zatca_logs.no_debit_note_logs') ?></td></tr>
      <?php else: ?>
        <?php $renderRows($debitNoteLogs, 'debit_note', 'note_number', 'debitNote', 'debit_note_id'); ?>
      <?php endif; ?>
    </tbody>
  </table>
  @include('admin.partials.pagination', ['paginator' => $debitNoteLogs])
<?php endif; ?>
@endsection
