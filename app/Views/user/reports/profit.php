<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Business Reports</h1>
</div>

<div class="tabs">
  <a href="/app/reports">Performance</a>
  <a href="/app/reports/profit" class="active">Profit Tracker</a>
  <a href="/app/reports/tax">Tax Summary</a>
  <a href="/app/reports/retention">Retention Ledger</a>
</div>

<p class="help-text" style="margin-bottom:16px;">Profit is calculated as payments collected minus project budget — a simplified view based on budget vs. revenue, since detailed expense tracking isn't captured per project yet.</p>

<?php if (empty($rows)): ?>
  <div class="card empty-state"><div class="icon">📈</div><h3>No projects yet</h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Project</th><th>Budget</th><th>Invoiced</th><th>Paid</th><th>Profit</th><th>Margin</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/app/projects/<?= $r['project']['id'] ?>"><?= View::e($r['project']['name']) ?></a></td>
        <td><?= View::money($r['budget']) ?></td>
        <td><?= View::money($r['invoiced']) ?></td>
        <td><?= View::money($r['paid']) ?></td>
        <td style="color:<?= $r['profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-weight:700;"><?= View::money($r['profit']) ?></td>
        <td><?= $r['margin'] === null ? '—' : $r['margin'] . '%' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
