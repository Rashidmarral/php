<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= View::e($estimate['title']) ?></h1>
  <a href="/portal" class="btn btn-light">← Back</a>
</div>

<div class="card" style="max-width:820px;">
  <span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$estimate['status']] ?? 'gray' ?>" style="margin-bottom:14px;display:inline-block;"><?= View::e($estimate['status']) ?></span>
  <table class="data">
    <thead><tr><th>Description</th><th>Qty</th><th>Unit cost</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr><td><?= View::e($it['description']) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_cost']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total-row" style="margin-top:14px;">Total: <?= View::money((float)$estimate['total']) ?></div>
</div>
