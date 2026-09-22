@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($invoice['invoice_number']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?><?php if ($project): ?> · <?= t('common.project') ?>: <a href="/app/projects/<?= $project['id'] ?>"><?= e(local($project, 'name')) ?></a><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$invoice['status']] ?? 'yellow' ?>" style="font-size:13px;padding:6px 14px;"><?= e($invoice['status']) ?></span>
    <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/delete" onsubmit="return confirm('<?= t('user.invoices.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<?php if ($invoice['approval_status'] === 'pending'): ?>
  <div class="alert" style="max-width:820px;background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
    <strong><?= t('user.invoices.approval_pending') ?></strong>
    <p class="help-text" style="margin-top:4px;color:inherit;"><?= t('user.invoices.approval_pending_hint') ?></p>
    <?php if (auth()->user()->can('approve_documents')): ?>
      <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:12px;">
        <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/approve">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= t('user.invoices.approve') ?></button>
        </form>
        <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/reject" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
          <?= csrf_field() ?>
          <div class="form-group" style="margin:0;">
            <input type="text" name="reason" placeholder="<?= t('user.invoices.rejection_reason_placeholder') ?>" style="min-width:220px;">
          </div>
          <button type="submit" class="btn btn-danger"><?= t('user.invoices.reject') ?></button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ((int)($invoice['approval_requested_by'] ?? 0) === (int) auth()->id()): ?>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;">
        <?php if ($approverWhatsappLink): ?>
          <a href="<?= e($approverWhatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('user.invoices.notify_approver_whatsapp') ?></a>
        <?php endif; ?>
        <?php if ($whatsappApiConfigured && $approverWhatsappLink): ?>
          <button type="button" onclick="document.getElementById('whatsapp-approver-form').submit();" class="btn btn-sm btn-outline">🤖 <?= t('user.invoices.notify_approver_whatsapp') ?></button>
        <?php endif; ?>
      </div>
      <form id="whatsapp-approver-form" method="post" action="/app/invoices/<?= $invoice['id'] ?>/notify-approver" style="display:none;"><?= csrf_field() ?></form>
    <?php endif; ?>
  </div>
<?php elseif ($invoice['approval_status'] === 'rejected'): ?>
  <div class="alert alert-error" style="max-width:820px;">
    <strong><?= t('user.invoices.approval_rejected') ?></strong>
    <?php if (!empty($invoice['rejection_reason'])): ?>
      <p class="help-text" style="margin-top:4px;color:inherit;"><?= t('common.reason') ?>: <?= e($invoice['rejection_reason']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="get" action="/app/invoices/<?= $invoice['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;align-items:end;margin-bottom:20px;max-width:820px;flex-wrap:wrap;">
  <?php if (!$hasCustomTemplate): ?>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.pdf_template') ?></label>
      <select name="template">
        <option value="modern" <?= $activeTemplate === 'modern' ? 'selected' : '' ?>><?= t('common.pdf_template_modern') ?></option>
        <option value="classic" <?= $activeTemplate === 'classic' ? 'selected' : '' ?>><?= t('common.pdf_template_classic') ?></option>
        <option value="minimal" <?= $activeTemplate === 'minimal' ? 'selected' : '' ?>><?= t('common.pdf_template_minimal') ?></option>
        <option value="bold" <?= $activeTemplate === 'bold' ? 'selected' : '' ?>><?= t('common.pdf_template_bold') ?></option>
        <option value="elegant" <?= $activeTemplate === 'elegant' ? 'selected' : '' ?>><?= t('common.pdf_template_elegant') ?></option>
        <option value="saudi" <?= $activeTemplate === 'saudi' ? 'selected' : '' ?>><?= t('common.pdf_template_saudi') ?></option>
      </select>
    </div>
  <?php else: ?>
    <p class="help-text" style="margin:0;max-width:260px;"><?= t('common.pdf_custom_template_active', ['url' => '/app/settings/invoice-templates/invoice']) ?></p>
  <?php endif; ?>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.language') ?></label>
    <select name="lang"><option value="en"><?= t('common.english') ?></option><option value="ar"><?= t('common.arabic') ?></option></select>
  </div>
  <button type="submit" class="btn btn-outline">⬇ <?= t('common.download_pdf') ?></button>
  <?php if ($approvalBlocked): ?>
    <span class="help-text"><?= t('user.invoices.send_blocked_hint') ?></span>
  <?php else: ?>
    <?php if ($whatsappLink): ?>
      <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('common.send_whatsapp') ?></a>
    <?php endif; ?>
    <?php if ($whatsappApiConfigured && $client && !empty($client['phone'])): ?>
      <button type="button" onclick="document.getElementById('whatsapp-auto-form').submit();" class="btn btn-outline">🤖 <?= t('user.invoices.auto_notify_whatsapp') ?></button>
    <?php endif; ?>
    <?php if ($smsApiConfigured && $client && !empty($client['phone'])): ?>
      <button type="button" onclick="document.getElementById('sms-auto-form').submit();" class="btn btn-outline">📱 <?= t('user.invoices.send_sms') ?></button>
    <?php endif; ?>
    <?php if ($isOverdue && $reminderWhatsappLink): ?>
      <a href="<?= e($reminderWhatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">⏰ <?= t('user.invoices.send_payment_reminder') ?></a>
    <?php endif; ?>
    <?php if ($isOverdue && $whatsappApiConfigured && $reminderWhatsappLink): ?>
      <button type="button" onclick="document.getElementById('whatsapp-reminder-form').submit();" class="btn btn-outline">🤖 <?= t('user.invoices.send_payment_reminder') ?></button>
    <?php endif; ?>
  <?php endif; ?>
</form>
<form id="whatsapp-auto-form" method="post" action="/app/invoices/<?= $invoice['id'] ?>/send-whatsapp" style="display:none;"><?= csrf_field() ?></form>
<form id="sms-auto-form" method="post" action="/app/invoices/<?= $invoice['id'] ?>/send-sms" style="display:none;"><?= csrf_field() ?></form>
<form id="whatsapp-reminder-form" method="post" action="/app/invoices/<?= $invoice['id'] ?>/send-payment-reminder" style="display:none;"><?= csrf_field() ?></form>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= e(local($it, 'description')) ?></td><td><?= e($it['qty']) ?></td><td><?= money((float)$it['unit_price']) ?></td><td><?= money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!empty($invoice['vat_amount'])): $subtotal = (float)$invoice['total'] - (float)$invoice['vat_amount']; ?>
    <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:14px;">
      <?= t('common.subtotal') ?>: <?= money($subtotal) ?><br>
      <?= t('common.vat') ?> (<?= e((string)$invoice['vat_rate']) ?>%): <?= money((float)$invoice['vat_amount']) ?>
    </div>
  <?php endif; ?>
  <div class="total-row" style="margin-top:6px;"><?= t('common.total') ?>: <?= money((float)$invoice['total']) ?></div>
  <?php if ($invoice['due_date']): ?><p class="help-text"><?= t('common.due') ?>: <?= e($invoice['due_date']) ?></p><?php endif; ?>
</div>

<?php if ((float) $invoice['retention_amount'] > 0): ?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3><?= t('user.invoices.retention') ?></h3>
    <p class="help-text">
      <?= e((string)$invoice['retention_percent']) ?>% <?= t('user.invoices.withheld') ?>:
      <strong><?= money((float)$invoice['retention_amount']) ?></strong> ·
      <?= t('user.invoices.net_payable') ?>: <strong><?= money((float)$invoice['total'] - (float)$invoice['retention_amount']) ?></strong>
    </p>
    <?php if ($invoice['retention_released']): ?>
      <p class="help-text" style="color:var(--success);">✅ <?= t('user.invoices.released_on') ?> <?= e($invoice['retention_released_at']) ?></p>
    <?php else: ?>
      <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/release-retention" onsubmit="return confirm('<?= t('user.invoices.release_retention_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline"><?= t('user.invoices.mark_retention_released') ?></button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($zatcaQr): ?>
  <div class="card" style="max-width:820px;margin-top:20px;display:flex;gap:16px;align-items:center;">
    <img src="<?= $zatcaQr ?>" width="110" height="110" alt="<?= t('user.invoices.zatca_qr_alt') ?>">
    <div>
      <h3 style="margin-bottom:4px;"><?= t('user.invoices.zatca_qr') ?></h3>
      <p class="help-text"><?= t('user.invoices.zatca_qr_hint') ?></p>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert-error" style="max-width:820px;margin-top:20px;">
    <?= t('user.invoices.no_vat_number') ?> <a href="/app/settings"><?= t('side.settings') ?></a>.
  </div>
<?php endif; ?>

<?php if (!empty($invoice['zatca_uuid'])):
  $zatcaStatusLabels = [
    'not_submitted' => [t('user.invoices.zatca_not_submitted'), 'gray'],
    'reported' => [t('user.invoices.zatca_reported'), 'green'],
    'cleared' => [t('user.invoices.zatca_cleared'), 'green'],
    'failed' => [t('user.invoices.zatca_failed'), 'red'],
  ];
  $zStatus = $invoice['zatca_status'] ?: 'not_submitted';
  [$zLabel, $zColor] = $zatcaStatusLabels[$zStatus] ?? [$zStatus, 'gray'];
  $companyLive = ($company['zatca_status'] ?? '') === 'onboarded';
?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3><?= t('user.invoices.zatca_phase2') ?></h3>
    <p class="help-text" style="margin-bottom:10px;">
      <?= t('common.status') ?>: <span class="badge badge-<?= $zColor ?>"><?= e($zLabel) ?></span>
      · ICV #<?= (int) $invoice['zatca_icv'] ?>
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <a href="/app/invoices/<?= $invoice['id'] ?>/xml" class="btn btn-outline">⬇ <?= t('user.invoices.download_ubl_xml') ?></a>
      <?php if (\App\Support\Feature::allows('zatca_phase2') && $companyLive && !in_array($zStatus, ['reported', 'cleared'], true)): ?>
        <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/submit-zatca" onsubmit="return confirm('<?= t('user.invoices.submit_zatca_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= t('user.invoices.submit_to_zatca') ?></button>
        </form>
      <?php elseif (!$companyLive): ?>
        <span class="help-text"><?= t('user.invoices.zatca_not_activated') ?></span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('common.client_link') ?></h3>
  <p class="help-text"><?= t('common.client_link_hint') ?></p>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
    <input type="text" readonly value="<?= e($shareUrl) ?>" style="flex:1;min-width:260px;" onclick="this.select();">
    <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText('<?= e($shareUrl) ?>'); this.textContent='<?= t('common.copied') ?>';"><?= t('common.copy_link') ?></button>
    <a href="<?= e($shareUrl) ?>" target="_blank" class="btn btn-sm btn-outline"><?= t('common.preview') ?></a>
  </div>
</div>

<?php if ($invoice['zatca_status'] && in_array($invoice['zatca_status'], ['cleared', 'reported'], true)): ?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3><?= t('credit_note.corrections_title') ?></h3>
    <p class="help-text"><?= t('credit_note.remaining_creditable') ?>: <strong><?= money((float)$remainingCreditable) ?></strong></p>
    <div style="display:flex;gap:10px;margin:10px 0;flex-wrap:wrap;">
      <a href="/app/credit-notes/create?invoice_id=<?= $invoice['id'] ?>" class="btn btn-outline"><?= t('credit_note.issue') ?></a>
      <a href="/app/debit-notes/create?invoice_id=<?= $invoice['id'] ?>" class="btn btn-outline"><?= t('debit_note.issue') ?></a>
    </div>
    <?php if (!empty($creditNotes) || !empty($debitNotes)): ?>
      <table class="data">
        <thead><tr><th>#</th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th><th><?= t('user.invoices.zatca_phase2') ?></th></tr></thead>
        <tbody>
          <?php foreach ($creditNotes as $n): ?>
            <tr><td><a href="/app/credit-notes/<?= $n['id'] ?>"><?= e($n['note_number']) ?></a> (<?= t('credit_note.title') ?>)</td><td><?= e($n['status']) ?></td><td><?= money((float)$n['total']) ?></td><td><?= e($n['zatca_status'] ?: 'not_submitted') ?></td></tr>
          <?php endforeach; ?>
          <?php foreach ($debitNotes as $n): ?>
            <tr><td><a href="/app/debit-notes/<?= $n['id'] ?>"><?= e($n['note_number']) ?></a> (<?= t('debit_note.title') ?>)</td><td><?= e($n['status']) ?></td><td><?= money((float)$n['total']) ?></td><td><?= e($n['zatca_status'] ?: 'not_submitted') ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('common.update_status') ?></h3>
  <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/status" style="display:flex;gap:10px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="status">
        <?php foreach (['unpaid'=>t('user.invoices.status_unpaid'),'paid'=>t('user.invoices.status_paid'),'overdue'=>t('user.invoices.status_overdue')] as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $invoice['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.update') ?></button>
  </form>
</div>

@endsection
