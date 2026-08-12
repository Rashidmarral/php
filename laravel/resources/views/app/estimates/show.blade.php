@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e(local($estimate, 'title')) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?><?php if ($project): ?> · <?= t('common.project') ?>: <a href="/app/projects/<?= $project['id'] ?>"><?= e(local($project, 'name')) ?></a><?php endif; ?><?php if (!empty($estimate['building_type'])): ?> · <?= e($estimate['building_type']) ?><?php endif; ?><?php if (!empty($estimate['job_address'])): ?> · <?= e($estimate['job_address']) ?><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$estimate['status']] ?? 'gray' ?>" style="font-size:13px;padding:6px 14px;"><?= e($estimate['status']) ?></span>
    <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('<?= t('user.estimates.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<form method="get" action="/app/estimates/<?= $estimate['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;align-items:end;margin-bottom:20px;max-width:820px;">
  <div class="form-group" style="margin:0;">
    <label><?= t('common.pdf_template') ?></label>
    <select name="template">
      <option value="modern">Modern</option>
      <option value="classic">Classic</option>
      <option value="minimal">Minimal</option>
      <option value="bold">Bold</option>
      <option value="elegant">Elegant</option>
        <option value="saudi">Saudi (ZATCA bilingual)</option>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('common.language') ?></label>
    <select name="lang"><option value="en"><?= t('common.english') ?></option><option value="ar"><?= t('common.arabic') ?></option></select>
  </div>
  <button type="submit" class="btn btn-outline">⬇ <?= t('common.download_pdf') ?></button>
  <?php if ($whatsappLink): ?>
    <a href="<?= e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('common.send_whatsapp') ?></a>
  <?php endif; ?>
</form>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.type') ?></th><th><?= t('common.qty') ?></th><th>UOM</th><th><?= t('common.unit_cost') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
      <?php $lastSection = null; foreach ($items as $it): ?>
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
    <div><span>Cost subtotal</span><span><?= money((float)$estimate['subtotal']) ?></span></div>
    <div><span>Margin (<?= e($estimate['markup_percent']) ?>%)</span><span><?= money((float)$estimate['markup_amount']) ?></span></div>
    <?php if ((float)$estimate['tax_amount'] > 0 || $estimate['tax_rate_id']): ?>
      <div><span>Tax (<?= e($estimate['tax_percent']) ?>%)</span><span><?= money((float)$estimate['tax_amount']) ?></span></div>
    <?php endif; ?>
  </div>
  <div class="total-row" style="margin-top:8px;"><?= t('common.total') ?>: <?= money((float)$estimate['total']) ?></div>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3>Profit margin &amp; tax</h3>
  <form method="post" action="/app/estimates/<?= $estimate['id'] ?>/totals" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;">
      <label>Profit margin %</label>
      <input type="number" step="0.01" min="0" max="100" name="markup_percent" value="<?= e($estimate['markup_percent']) ?>" style="width:110px;">
    </div>
    <div class="form-group" style="margin:0;">
      <label>Tax rate</label>
      <select name="tax_rate_id">
        <option value="">No tax</option>
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
      <img src="<?= e($estimate['signature_data']) ?>" alt="Signature" style="max-width:240px;border:1px solid var(--border);border-radius:8px;margin-top:6px;background:#fff;">
    <?php endif; ?>
  <?php elseif ($estimate['status'] === 'declined'): ?>
    <p class="help-text" style="color:var(--danger);">❌ <?= t('user.estimates.declined_notice') ?></p>
  <?php else: ?>
    <p class="help-text"><?= t('user.estimates.signing_hint') ?></p>
  <?php endif; ?>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
    <input type="text" readonly value="<?= e($shareUrl) ?>" style="flex:1;min-width:260px;" onclick="this.select();">
    <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText('<?= e($shareUrl) ?>'); this.textContent='<?= t('common.copied') ?>';"><?= t('common.copy_link') ?></button>
    <a href="<?= e($shareUrl) ?>" target="_blank" class="btn btn-sm btn-outline"><?= t('common.preview') ?></a>
  </div>
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
