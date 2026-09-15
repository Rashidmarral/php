@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e(local($estimate, 'title')) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?><?php if ($project): ?> · <?= t('common.project') ?>: <a href="/app/projects/<?= $project['id'] ?>"><?= e(local($project, 'name')) ?></a><?php endif; ?><?php if (!empty($estimate['building_type'])): ?> · <?= e($estimate['building_type']) ?><?php endif; ?><?php if (!empty($estimate['job_address'])): ?> · <?= e($estimate['job_address']) ?><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <?php if ($isExpired): ?>
      <span class="badge badge-red" style="font-size:13px;padding:6px 14px;"><?= t('user.estimates.status_expired') ?></span>
    <?php else: ?>
      <span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$estimate['status']] ?? 'gray' ?>" style="font-size:13px;padding:6px 14px;"><?= e($estimate['status']) ?></span>
    <?php endif; ?>
    <?php if ($estimate['status'] !== 'accepted'): ?>
      <a href="/app/estimates/<?= $estimate['id'] ?>/edit" class="btn btn-outline"><?= t('common.edit') ?></a>
    <?php endif; ?>
    <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/duplicate">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline"><?= t('common.duplicate') ?></button>
    </form>
    <?php if ($estimate['status'] === 'accepted'): ?>
      <?php if ($convertedInvoice): ?>
        <a href="/app/invoices/<?= $convertedInvoice['id'] ?>" class="btn btn-primary"><?= t('user.estimates.view_invoice', ['number' => $convertedInvoice['invoice_number']]) ?></a>
      <?php else: ?>
        <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/convert-to-invoice">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= t('user.estimates.convert_to_invoice') ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
    <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('<?= t('user.estimates.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<?php if ($estimate['approval_status'] === 'pending'): ?>
  <div class="alert" style="max-width:820px;background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
    <strong><?= t('user.estimates.approval_pending') ?></strong>
    <p class="help-text" style="margin-top:4px;color:inherit;"><?= t('user.estimates.approval_pending_hint') ?></p>
    <?php if (auth()->user()->can('approve_documents')): ?>
      <div style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:12px;">
        <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/approve">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-primary"><?= t('user.estimates.approve') ?></button>
        </form>
        <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/reject" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
          <?= csrf_field() ?>
          <div class="form-group" style="margin:0;">
            <input type="text" name="reason" placeholder="<?= t('user.estimates.rejection_reason_placeholder') ?>" style="min-width:220px;">
          </div>
          <button type="submit" class="btn btn-danger"><?= t('user.estimates.reject') ?></button>
        </form>
      </div>
    <?php endif; ?>
    <?php if ((int)($estimate['approval_requested_by'] ?? 0) === (int) auth()->id()): ?>
      <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;">
        <?php if ($approverWhatsappLink): ?>
          <a href="<?= e($approverWhatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('user.estimates.notify_approver_whatsapp') ?></a>
        <?php endif; ?>
        <?php if ($whatsappApiConfigured && $approverWhatsappLink): ?>
          <button type="button" onclick="document.getElementById('whatsapp-approver-form').submit();" class="btn btn-sm btn-outline">🤖 <?= t('user.estimates.notify_approver_whatsapp') ?></button>
        <?php endif; ?>
      </div>
      <form id="whatsapp-approver-form" method="post" action="/app/estimates/<?= $estimate['id'] ?>/notify-approver" style="display:none;"><?= csrf_field() ?></form>
    <?php endif; ?>
  </div>
<?php elseif ($estimate['approval_status'] === 'rejected'): ?>
  <div class="alert alert-error" style="max-width:820px;">
    <strong><?= t('user.estimates.approval_rejected') ?></strong>
    <?php if (!empty($estimate['rejection_reason'])): ?>
      <p class="help-text" style="margin-top:4px;color:inherit;"><?= t('common.reason') ?>: <?= e($estimate['rejection_reason']) ?></p>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form method="get" action="/app/estimates/<?= $estimate['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;align-items:end;margin-bottom:20px;max-width:820px;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label><?= t('common.pdf_template') ?></label>
    <select name="template">
      <option value="modern"><?= t('common.pdf_template_modern') ?></option>
      <option value="classic"><?= t('common.pdf_template_classic') ?></option>
      <option value="minimal"><?= t('common.pdf_template_minimal') ?></option>
      <option value="bold"><?= t('common.pdf_template_bold') ?></option>
      <option value="elegant"><?= t('common.pdf_template_elegant') ?></option>
      <option value="saudi"><?= t('common.pdf_template_saudi') ?></option>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.language') ?></label>
    <select name="lang"><option value="en"><?= t('common.english') ?></option><option value="ar"><?= t('common.arabic') ?></option></select>
  </div>
  <button type="submit" class="btn btn-outline">⬇ <?= t('common.download_pdf') ?></button>
  <?php if ($approvalBlocked): ?>
    <span class="help-text"><?= t('user.estimates.send_blocked_hint') ?></span>
  <?php else: ?>
    <?php if ($whatsappLink): ?>
      <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('common.send_whatsapp') ?></a>
    <?php endif; ?>
    <?php if ($smsApiConfigured && $client && !empty($client['phone'])): ?>
      <button type="button" onclick="document.getElementById('sms-auto-form').submit();" class="btn btn-outline">📱 <?= t('user.estimates.send_sms') ?></button>
    <?php endif; ?>
  <?php endif; ?>
</form>
<form id="sms-auto-form" method="post" action="/app/estimates/<?= $estimate['id'] ?>/send-sms" style="display:none;"><?= csrf_field() ?></form>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.type') ?></th><th><?= t('common.qty') ?></th><th>UOM</th><th><?= t('common.unit_cost') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
      <?php $lastSection = null; foreach ($items as $it): if (!empty($it['is_optional'])) continue; ?>
        <?php if (!empty($it['section_title']) && $it['section_title'] !== $lastSection): $lastSection = $it['section_title']; ?>
          <tr style="background:#fafcfb;"><td colspan="6"><strong><?= e(local($it, 'section_title')) ?></strong></td></tr>
        <?php endif; ?>
        <tr>
          <td><?= e(local($it, 'description')) ?></td>
          <td><span class="badge badge-<?= $it['item_type']==='labor'?'yellow':'gray' ?>"><?= e(ucfirst($it['item_type'])) ?></span></td>
          <td><?= e($it['qty']) ?></td>
          <td><?= e($it['uom']) ?></td>
          <td><?= money((float)$it['unit_cost']) ?></td>
          <td><?= money((float)$it['total']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="breakdown" style="margin-top:14px;max-width:320px;margin-inline-start:auto;font-size:14px;">
    <div><span><?= t('user.estimates.cost_subtotal') ?></span><span><?= money((float)$estimate['subtotal']) ?></span></div>
    <div><span><?= t('user.estimates.margin_with_pct', ['pct' => e($estimate['markup_percent'])]) ?></span><span><?= money((float)$estimate['markup_amount']) ?></span></div>
    <?php if ((float)$estimate['tax_amount'] > 0 || $estimate['tax_rate_id']): ?>
      <div><span><?= t('user.estimates.tax_with_pct', ['pct' => e($estimate['tax_percent'])]) ?></span><span><?= money((float)$estimate['tax_amount']) ?></span></div>
    <?php endif; ?>
  </div>
  <div class="total-row" style="margin-top:8px;"><?= t('common.total') ?>: <?= money((float)$estimate['total']) ?></div>

  <?php if (!empty($optionalItems)): ?>
    <div style="margin-top:20px;padding-top:16px;border-top:1px solid var(--border);">
      <h4 style="margin:0 0 8px;"><?= t('user.estimates.optional_addons_heading') ?></h4>
      <table class="data">
        <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.total') ?></th><th><?= t('common.status') ?></th></tr></thead>
        <tbody>
          <?php foreach ($optionalItems as $it): ?>
            <tr>
              <td><?= e(local($it, 'description')) ?></td>
              <td><?= e($it['qty']) ?></td>
              <td><?= money((float)$it['total']) ?></td>
              <td>
                <?php if ($it['client_selected'] === null): ?>
                  <span class="badge badge-gray"><?= t('user.estimates.addon_pending') ?></span>
                <?php elseif ($it['client_selected']): ?>
                  <span class="badge badge-green"><?= t('user.estimates.addon_selected') ?></span>
                <?php else: ?>
                  <span class="badge badge-red"><?= t('user.estimates.addon_declined') ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if ($estimate['status'] === 'accepted' && $estimate['accepted_total'] !== null && (float)$estimate['accepted_total'] !== (float)$estimate['total']): ?>
        <div class="total-row" style="margin-top:8px;"><?= t('user.estimates.accepted_total') ?>: <?= money((float)$estimate['accepted_total']) ?></div>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('user.estimates.profit_tax_title') ?></h3>
  <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/totals" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.estimates.profit_margin_percent') ?></label>
      <input type="number" step="0.01" min="0" max="100" name="markup_percent" value="<?= e($estimate['markup_percent']) ?>" style="width:110px;">
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.estimates.tax_rate') ?></label>
      <select name="tax_rate_id">
        <option value=""><?= t('user.estimates.no_tax') ?></option>
        <?php foreach ($taxRates as $tr): ?>
          <option value="<?= $tr['id'] ?>" <?= (string)$estimate['tax_rate_id'] === (string)$tr['id'] ? 'selected' : '' ?>><?= e($tr['name']) ?> (<?= e($tr['rate_percent']) ?>%)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.update') ?></button>
  </form>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('user.estimates.client_signing_link') ?></h3>
  <?php if ($estimate['status'] === 'accepted' && !empty($estimate['signed_by_name'])): ?>
    <p class="help-text" style="color:var(--success);">✅ <?= t('user.estimates.signed_by') ?> <strong><?= e($estimate['signed_by_name']) ?></strong> <?= t('user.estimates.on') ?> <?= e($estimate['signed_at']) ?></p>
    <?php if (!empty($estimate['signature_data'])): ?>
      <img src="<?= e($estimate['signature_data']) ?>" alt="<?= t('user.estimates.signature_alt') ?>" style="max-width:240px;border:1px solid var(--border);border-radius:8px;margin-top:6px;background:#fff;">
    <?php endif; ?>
  <?php elseif ($estimate['status'] === 'declined'): ?>
    <p class="help-text" style="color:var(--danger);">❌ <?= t('user.estimates.declined_notice') ?></p>
  <?php else: ?>
    <p class="help-text"><?= t('user.estimates.signing_hint') ?></p>
  <?php endif; ?>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
    <input type="text" readonly value="<?= e($shareUrl) ?>" style="flex:1;min-width:260px;" onclick="this.select();">
    <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText('<?= e($shareUrl) ?>'); this.textContent='<?= t('common.copied') ?>';"><?= t('common.copy_link') ?></button>
    <a href="<?= e($shareUrl) ?>?preview=1" target="_blank" class="btn btn-sm btn-outline"><?= t('common.preview') ?></a>
  </div>
  <p class="help-text" style="margin-top:8px;">
    <?php if ((int) $estimate['view_count'] === 0): ?>
      <?= t('user.estimates.not_viewed') ?>
    <?php else: ?>
      <?= t('user.estimates.viewed_summary', ['count' => $estimate['view_count'], 'first' => $estimate['first_viewed_at'], 'last' => $estimate['last_viewed_at']]) ?>
    <?php endif; ?>
  </p>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3><?= t('common.update_status') ?></h3>
  <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/status" style="display:flex;gap:10px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="status">
        <?php foreach (['draft'=>t('user.estimates.status_draft'),'sent'=>t('user.estimates.status_sent'),'accepted'=>t('user.estimates.status_accepted'),'declined'=>t('user.estimates.status_declined')] as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $estimate['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.update') ?></button>
  </form>
</div>

@endsection
