<?php use App\Core\View; use App\Core\Csrf; use App\Core\Feature; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($invoice['invoice_number']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Client: <?= View::e($client ? View::local($client, 'name') : '—') ?><?php if ($project): ?> · Project: <a href="/app/projects/<?= $project['id'] ?>"><?= View::e(View::local($project, 'name')) ?></a><?php endif; ?></p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;">
    <span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$invoice['status']] ?? 'yellow' ?>" style="font-size:13px;padding:6px 14px;"><?= View::e($invoice['status']) ?></span>
    <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/delete" onsubmit="return confirm('Delete this invoice?');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>

<form method="get" action="/app/invoices/<?= $invoice['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;align-items:end;margin-bottom:20px;max-width:820px;">
  <div class="form-group" style="margin:0;">
    <label>PDF template</label>
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
    <label>Language</label>
    <select name="lang"><option value="en">English</option><option value="ar">Arabic</option></select>
  </div>
  <button type="submit" class="btn btn-outline">⬇ Download PDF</button>
  <?php if ($whatsappLink): ?>
    <a href="<?= View::e($whatsappLink) ?>" target="_blank" rel="noopener" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 Send via WhatsApp</a>
  <?php endif; ?>
  <?php if ($whatsappApiConfigured && $client && !empty($client['phone'])): ?>
    <button type="button" onclick="document.getElementById('whatsapp-auto-form').submit();" class="btn btn-outline">🤖 Auto-notify via WhatsApp</button>
  <?php endif; ?>
</form>
<form id="whatsapp-auto-form" method="post" action="/app/invoices/<?= $invoice['id'] ?>/send-whatsapp" style="display:none;"><?= Csrf::field() ?></form>

<div class="card" style="max-width:820px;">
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= View::e(View::local($it, 'description')) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_price']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!empty($invoice['vat_amount'])): $subtotal = (float)$invoice['total'] - (float)$invoice['vat_amount']; ?>
    <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:14px;">
      Subtotal: <?= View::money($subtotal) ?><br>
      VAT (<?= View::e((string)$invoice['vat_rate']) ?>%): <?= View::money((float)$invoice['vat_amount']) ?>
    </div>
  <?php endif; ?>
  <div class="total-row" style="margin-top:6px;">Total: <?= View::money((float)$invoice['total']) ?></div>
  <?php if ($invoice['due_date']): ?><p class="help-text">Due: <?= View::e($invoice['due_date']) ?></p><?php endif; ?>
</div>

<?php if ((float) $invoice['retention_amount'] > 0): ?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3>Retention</h3>
    <p class="help-text">
      <?= View::e((string)$invoice['retention_percent']) ?>% withheld from this invoice:
      <strong><?= View::money((float)$invoice['retention_amount']) ?></strong> ·
      Net payable: <strong><?= View::money((float)$invoice['total'] - (float)$invoice['retention_amount']) ?></strong>
    </p>
    <?php if ($invoice['retention_released']): ?>
      <p class="help-text" style="color:var(--success);">✅ Released on <?= View::e($invoice['retention_released_at']) ?></p>
    <?php else: ?>
      <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/release-retention" onsubmit="return confirm('Mark this retention as released to the client?');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-outline">Mark retention released</button>
      </form>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php if ($zatcaQr): ?>
  <div class="card" style="max-width:820px;margin-top:20px;display:flex;gap:16px;align-items:center;">
    <img src="<?= $zatcaQr ?>" width="110" height="110" alt="ZATCA QR Code">
    <div>
      <h3 style="margin-bottom:4px;">ZATCA QR Code</h3>
      <p class="help-text">Phase 1 compliant — encodes seller name, VAT number, timestamp, invoice total, and VAT amount. Included automatically on the PDF.</p>
    </div>
  </div>
<?php else: ?>
  <div class="alert alert-error" style="max-width:820px;margin-top:20px;">
    No VAT number set on your company profile — the ZATCA QR code can't be generated. Add one in <a href="/app/settings">Settings</a>.
  </div>
<?php endif; ?>

<?php if (!empty($invoice['zatca_uuid'])):
  $zatcaStatusLabels = [
    'not_submitted' => ['Not yet submitted to ZATCA', 'gray'],
    'reported' => ['Reported to ZATCA', 'green'],
    'failed' => ['ZATCA submission failed', 'red'],
  ];
  $zStatus = $invoice['zatca_status'] ?: 'not_submitted';
  [$zLabel, $zColor] = $zatcaStatusLabels[$zStatus] ?? [$zStatus, 'gray'];
  $companyLive = ($company['zatca_status'] ?? '') === 'active';
?>
  <div class="card" style="max-width:820px;margin-top:20px;">
    <h3>ZATCA Phase 2 (Fatoora integration)</h3>
    <p class="help-text" style="margin-bottom:10px;">
      Status: <span class="badge badge-<?= $zColor ?>"><?= View::e($zLabel) ?></span>
      · ICV #<?= (int) $invoice['zatca_icv'] ?>
    </p>
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
      <a href="/app/invoices/<?= $invoice['id'] ?>/xml" class="btn btn-outline">⬇ Download UBL XML</a>
      <?php if (Feature::allows('zatca_phase2') && $companyLive && $zStatus !== 'reported'): ?>
        <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/submit-zatca" onsubmit="return confirm('Submit this invoice to ZATCA now? This cannot be undone.');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-primary">Submit to ZATCA</button>
        </form>
      <?php elseif (!$companyLive): ?>
        <span class="help-text">ZATCA Phase 2 isn't activated for your company yet — ask your platform administrator.</span>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3>Client link</h3>
  <p class="help-text">Send this link to your client so they can view and download the invoice without needing an account.</p>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:8px;">
    <input type="text" readonly value="<?= View::e($shareUrl) ?>" style="flex:1;min-width:260px;" onclick="this.select();">
    <button type="button" class="btn btn-sm btn-outline" onclick="navigator.clipboard.writeText('<?= View::e($shareUrl) ?>'); this.textContent='Copied!';">Copy link</button>
    <a href="<?= View::e($shareUrl) ?>" target="_blank" class="btn btn-sm btn-outline">Preview →</a>
  </div>
</div>

<div class="card" style="max-width:820px;margin-top:20px;">
  <h3>Update status</h3>
  <form method="post" action="/app/invoices/<?= $invoice['id'] ?>/status" style="display:flex;gap:10px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="status">
        <?php foreach (['unpaid'=>'Unpaid','paid'=>'Paid','overdue'=>'Overdue'] as $val=>$label): ?>
          <option value="<?= $val ?>" <?= $invoice['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Update</button>
  </form>
</div>
