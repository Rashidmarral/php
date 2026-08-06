<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Payments</h1>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(2,220px);">
  <div class="kpi"><div class="label">Total collected</div><div class="value"><?= View::money($total) ?></div></div>
  <div class="kpi"><div class="label">Pending approval</div><div class="value"><?= $pendingCount ?></div></div>
</div>

<?php if (empty($payments)): ?>
  <div class="card empty-state"><div class="icon">💵</div><h3>No payments yet</h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Date</th><th>Company</th><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($payments as $p): ?>
      <tr>
        <td><?= View::e($p['created_at']) ?></td>
        <td><a href="/admin/companies/<?= $p['company_id'] ?>"><?= View::e($p['company_name']) ?></a></td>
        <td><?= View::e($p['reference']) ?></td>
        <td><?= View::e(strtoupper($p['method'])) ?></td>
        <td><?= View::money((float)$p['amount']) ?></td>
        <td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':'red') ?>"><?= View::e($p['status']) ?></span></td>
        <td style="display:flex;gap:6px;">
          <?php if ($p['status'] === 'pending'): ?>
            <form method="post" action="/admin/payments/<?= $p['id'] ?>/approve" onsubmit="return confirm('Approve this payment and activate the company plan?');">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-primary">Approve</button>
            </form>
            <form method="post" action="/admin/payments/<?= $p['id'] ?>/reject" onsubmit="return confirm('Reject this payment?');">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-light">Reject</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
