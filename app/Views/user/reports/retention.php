<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Business Reports</h1>
</div>

<div class="tabs">
  <a href="/app/reports">Performance</a>
  <a href="/app/reports/profit">Profit Tracker</a>
  <a href="/app/reports/tax">Tax Summary</a>
  <a href="/app/reports/retention" class="active">Retention Ledger</a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;">A percentage withheld from what the client pays on each invoice, kept as security until the defects liability period ends — track what's still outstanding and mark it released once you've paid it back.</p>

<div class="kpi-grid" style="grid-template-columns:repeat(2,220px);">
  <div class="kpi"><div class="label">Outstanding retention</div><div class="value"><?= View::money($outstanding) ?></div></div>
  <div class="kpi"><div class="label">Released</div><div class="value"><?= View::money($released) ?></div></div>
</div>

<?php if (empty($rows)): ?>
  <div class="card empty-state">
    <div class="icon">🔒</div>
    <h3>No retention withheld yet</h3>
    <p>Set a retention % when creating an invoice — common on Saudi contracts (5–10%), released after the defects liability period.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Invoice</th><th>Client</th><th>Retention %</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/app/invoices/<?= $r['id'] ?>"><?= View::e($r['invoice_number']) ?></a></td>
        <td><?= View::e($r['client_name'] ?? '—') ?></td>
        <td><?= View::e((string)$r['retention_percent']) ?>%</td>
        <td><?= View::money((float)$r['retention_amount']) ?></td>
        <td>
          <?php if ($r['retention_released']): ?>
            <span class="badge badge-green">Released <?= View::e($r['retention_released_at']) ?></span>
          <?php else: ?>
            <span class="badge badge-yellow">Outstanding</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!$r['retention_released']): ?>
            <form method="post" action="/app/invoices/<?= $r['id'] ?>/release-retention" onsubmit="return confirm('Mark this retention as released?');">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-outline">Mark released</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
