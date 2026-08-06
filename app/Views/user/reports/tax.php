<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Business Reports</h1>
</div>

<div class="tabs">
  <a href="/app/reports">Performance</a>
  <a href="/app/reports/profit">Profit Tracker</a>
  <a href="/app/reports/tax" class="active">Tax Summary</a>
  <a href="/app/reports/retention">Retention Ledger</a>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="kpi"><div class="label">Invoices with VAT</div><div class="value"><?= $invoiceCount ?></div></div>
  <div class="kpi"><div class="label">Taxable amount</div><div class="value"><?= View::money($totalTaxable) ?></div></div>
  <div class="kpi"><div class="label">VAT collected</div><div class="value"><?= View::money($totalVat) ?></div></div>
</div>

<?php if (empty($byMonth)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <h3>No VAT-inclusive invoices yet</h3>
    <p>Invoices created with "Apply VAT" checked will show up here, grouped by month.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Month</th><th>Invoices</th><th>Taxable amount</th><th>VAT collected</th><th>Total</th></tr></thead>
    <tbody>
    <?php foreach ($byMonth as $month => $data): ?>
      <tr>
        <td><?= View::e(date('F Y', strtotime($month . '-01'))) ?></td>
        <td><?= $data['count'] ?></td>
        <td><?= View::money($data['subtotal']) ?></td>
        <td><?= View::money($data['vat']) ?></td>
        <td><?= View::money($data['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
