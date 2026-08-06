<?php use App\Core\View; ?>
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">
    <div class="card" style="padding:32px;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div>
          <div class="eyebrow">Invoice from <?= View::e($company['name'] ?? '') ?></div>
          <h1 style="margin-top:4px;"><?= View::e($invoice['invoice_number']) ?></h1>
          <p class="help-text">Billed to <?= View::e($client['name'] ?? 'you') ?> · <?= View::e($invoice['created_at']) ?></p>
        </div>
        <span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$invoice['status']] ?? 'yellow' ?>" style="font-size:13px;padding:6px 14px;"><?= View::e(ucfirst($invoice['status'])) ?></span>
      </div>

      <table class="data" style="margin-top:24px;">
        <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= View::e($it['description']) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_price']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
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

      <a href="/i/<?= View::e($invoice['share_token']) ?>/pdf" target="_blank" class="btn btn-outline" style="margin-top:16px;">⬇ Download PDF</a>
    </div>

    <?php if ($zatcaQr): ?>
      <div class="card" style="margin-top:20px;padding:24px;display:flex;gap:16px;align-items:center;">
        <img src="<?= $zatcaQr ?>" width="90" height="90" alt="ZATCA QR Code">
        <div>
          <h3 style="margin-bottom:4px;font-size:15px;">ZATCA QR Code</h3>
          <p class="help-text">Scan to verify this invoice's seller, VAT number, timestamp, and total.</p>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>
