@extends('layouts.app')

@section('content')
<?php
$statusClasses = ['cleared' => 'green', 'reported' => 'green', 'failed' => 'red', 'queued' => 'yellow'];
$statusLabels = ['cleared' => t('user.zatca.status_cleared'), 'reported' => t('user.zatca.status_reported'), 'failed' => t('user.zatca.status_failed'), 'queued' => t('user.zatca.status_queued')];
$renderRows = function ($logs, string $type, string $numberField, string $relation, string $fkColumn) use ($statusClasses, $statusLabels) {
    foreach ($logs as $log) {
        $document = $log->{$relation};
        $number = $document?->{$numberField} ?? '#' . $log->{$fkColumn};
        echo '<tr>';
        echo '<td><a href="/app/zatca/logs/' . $type . '/' . $log->id . '">' . e($number) . '</a></td>';
        echo '<td>' . e(strtoupper($log->invoice_type) . ' · ' . ucfirst($log->direction)) . '</td>';
        echo '<td class="help-text">' . ($log->submitted_at ? e($log->submitted_at->format('Y-m-d H:i')) : '—') . '</td>';
        echo '<td><span class="badge badge-' . ($statusClasses[$log->status] ?? 'gray') . '">' . e($statusLabels[$log->status] ?? $log->status) . '</span></td>';
        echo '<td>' . ($log->error_message ? '<span style="color:var(--danger);">' . e(\Illuminate\Support\Str::limit($log->error_message, 60)) . '</span>' : '<span class="help-text">—</span>') . '</td>';
        echo '</tr>';
    }
};
?>
<div class="page-head">
  <h1><?= t('user.zatca.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('user.zatca.dashboard_hint') ?></p>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi"><div class="label"><?= t('user.zatca.cleared') ?></div><div class="value"><?= number_format($stats['cleared']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.zatca.reported') ?></div><div class="value"><?= number_format($stats['reported']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.zatca.failed') ?></div><div class="value"><?= number_format($stats['failed']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.zatca.queued') ?></div><div class="value"><?= number_format($stats['queued']) ?></div></div>
</div>

<h3 style="margin:0 0 10px;"><?= t('user.zatca.section_invoices') ?></h3>
<table class="data">
  <thead><tr><th><?= t('user.zatca.document') ?></th><th><?= t('user.zatca.invoice_type') ?></th><th><?= t('user.zatca.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('user.zatca.error_message') ?></th></tr></thead>
  <tbody>
    <?php if ($invoiceLogs->isEmpty()): ?>
      <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;"><?= t('user.zatca.no_invoice_logs') ?></td></tr>
    <?php else: ?>
      <?php $renderRows($invoiceLogs, 'invoice', 'invoice_number', 'invoice', 'invoice_id'); ?>
    <?php endif; ?>
  </tbody>
</table>
@include('app.partials.pagination', ['paginator' => $invoiceLogs])

<h3 style="margin:28px 0 10px;"><?= t('user.zatca.section_credit_notes') ?></h3>
<table class="data">
  <thead><tr><th><?= t('user.zatca.document') ?></th><th><?= t('user.zatca.invoice_type') ?></th><th><?= t('user.zatca.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('user.zatca.error_message') ?></th></tr></thead>
  <tbody>
    <?php if ($creditNoteLogs->isEmpty()): ?>
      <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;"><?= t('user.zatca.no_credit_note_logs') ?></td></tr>
    <?php else: ?>
      <?php $renderRows($creditNoteLogs, 'credit_note', 'note_number', 'creditNote', 'credit_note_id'); ?>
    <?php endif; ?>
  </tbody>
</table>
@include('app.partials.pagination', ['paginator' => $creditNoteLogs])

<h3 style="margin:28px 0 10px;"><?= t('user.zatca.section_debit_notes') ?></h3>
<table class="data">
  <thead><tr><th><?= t('user.zatca.document') ?></th><th><?= t('user.zatca.invoice_type') ?></th><th><?= t('user.zatca.submission_date') ?></th><th><?= t('common.status') ?></th><th><?= t('user.zatca.error_message') ?></th></tr></thead>
  <tbody>
    <?php if ($debitNoteLogs->isEmpty()): ?>
      <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;"><?= t('user.zatca.no_debit_note_logs') ?></td></tr>
    <?php else: ?>
      <?php $renderRows($debitNoteLogs, 'debit_note', 'note_number', 'debitNote', 'debit_note_id'); ?>
    <?php endif; ?>
  </tbody>
</table>
@include('app.partials.pagination', ['paginator' => $debitNoteLogs])
@endsection
