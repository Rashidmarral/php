@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($debitNote['note_number']) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?= t('credit_note.against_invoice') ?> <a href="/app/invoices/<?= $invoice->id ?>"><?= e($invoice->invoice_number) ?></a>
      · <?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= $debitNote['status'] === 'void' ? 'red' : 'green' ?>" style="font-size:13px;padding:6px 14px;"><?= e($debitNote['status']) ?></span>
    <?php if ($debitNote['status'] !== 'void'): ?>
      <form method="post" action="/app/debit-notes/<?= $debitNote['id'] ?>/void" onsubmit="return confirm('<?= t('credit_note.void_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger"><?= t('credit_note.void') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($debitNote['reason'])): ?>
  <p class="help-text" style="max-width:820px;"><?= t('credit_note.reason') ?>: <?= e($debitNote['reason']) ?></p>
<?php endif; ?>

<form method="get" action="/app/debit-notes/<?= $debitNote['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;align-items:end;margin-bottom:20px;max-width:820px;">
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
    <p class="help-text" style="margin:0;max-width:260px;"><?= t('common.pdf_custom_template_active', ['url' => '/app/settings/invoice-templates/debit_note']) ?></p>
  <?php endif; ?>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.language') ?></label>
    <select name="lang"><option value="en"><?= t('common.english') ?></option><option value="ar"><?= t('common.arabic') ?></option></select>
  </div>
  <button type="submit" class="btn btn-outline">⬇ <?= t('common.download_pdf') ?></button>
</form>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= e(local($it, 'description')) ?></td><td><?= e($it['qty']) ?></td><td><?= money((float)$it['unit_price']) ?></td><td><?= money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:14px;">
    <?= t('common.subtotal') ?>: <?= money((float)$debitNote['subtotal']) ?><br>
    <?= t('common.vat') ?> (<?= e((string)$debitNote['vat_rate']) ?>%): <?= money((float)$debitNote['vat_amount']) ?>
  </div>
  <div class="total-row" style="margin-top:6px;"><?= t('common.total') ?>: <?= money((float)$debitNote['total']) ?></div>
</div>

<?php if (!empty($debitNote['zatca_uuid'])):
  $zatcaStatusLabels = [
    'not_submitted' => [t('user.invoices.zatca_not_submitted'), 'gray'],
    'reported' => [t('user.invoices.zatca_reported'), 'green'],
    'cleared' => [t('user.invoices.zatca_cleared'), 'green'],
    'failed' => [t('user.invoices.zatca_failed'), 'red'],
  ];
  $zStatus = $debitNote['zatca_status'] ?: 'not_submitted';
  [$zLabel, $zColor] = $zatcaStatusLabels[$zStatus] ?? [$zStatus, 'gray'];
  $company = \App\Models\Company::find($debitNote['company_id']);
  $companyLive = $company && $company->isZatcaOnboarded();
?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3><?= t('user.invoices.zatca_phase2') ?></h3>
    <p class="help-text" style="margin-bottom:10px;">
      <?= t('common.status') ?>: <span class="badge badge-<?= $zColor ?>"><?= e($zLabel) ?></span>
      · ICV #<?= (int) $debitNote['zatca_icv'] ?>
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <a href="/app/debit-notes/<?= $debitNote['id'] ?>/xml" class="btn btn-outline">⬇ <?= t('user.invoices.download_ubl_xml') ?></a>
      <?php if (\App\Support\Feature::allows('zatca_phase2') && $companyLive && !in_array($zStatus, ['reported', 'cleared'], true)): ?>
        <form method="post" action="/app/debit-notes/<?= $debitNote['id'] ?>/submit-zatca" onsubmit="return confirm('<?= t('user.invoices.submit_zatca_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= t('user.invoices.submit_to_zatca') ?></button>
        </form>
      <?php elseif (!$companyLive): ?>
        <span class="help-text"><?= t('user.invoices.zatca_not_activated') ?></span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

@endsection
