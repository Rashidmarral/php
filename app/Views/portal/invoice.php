<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= View::e($invoice['invoice_number']) ?></h1>
  <a href="/portal" class="btn btn-light">← Back</a>
</div>

<div class="card" style="max-width:820px;">
  <span class="badge badge-<?= ['paid'=>'green','overdue'=>'red'][$invoice['status']] ?? 'yellow' ?>" style="margin-bottom:14px;display:inline-block;"><?= View::e($invoice['status']) ?></span>
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit price</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= View::e($it['description']) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_price']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total-row" style="margin-top:14px;">Total: <?= View::money((float)$invoice['total']) ?></div>
  <?php if ($invoice['due_date']): ?><p class="help-text">Due: <?= View::e($invoice['due_date']) ?></p><?php endif; ?>
</div>
