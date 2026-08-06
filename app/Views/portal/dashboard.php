<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Welcome, <?= View::e($client['name']) ?></h1>
</div>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3>Projects</h3>
    <?php if (empty($projects)): ?>
      <p class="help-text">No projects yet.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Name</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($projects as $p): ?>
          <tr><td><a href="/portal/projects/<?= $p['id'] ?>"><?= View::e($p['name']) ?></a></td><td><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$p['status'])) ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card">
    <h3>Estimates</h3>
    <?php if (empty($estimates)): ?>
      <p class="help-text">No estimates yet.</p>
    <?php else: ?>
      <table class="data">
        <thead><tr><th>Title</th><th>Status</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($estimates as $e): ?>
          <tr><td><a href="/portal/estimates/<?= $e['id'] ?>"><?= View::e($e['title']) ?></a></td><td><span class="badge badge-gray"><?= View::e($e['status']) ?></span></td><td><?= View::money((float)$e['total']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <h3>Invoices</h3>
  <?php if (empty($invoices)): ?>
    <p class="help-text">No invoices yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>#</th><th>Status</th><th>Total</th><th>Due</th></tr></thead>
      <tbody>
      <?php foreach ($invoices as $i): ?>
        <tr>
          <td><a href="/portal/invoices/<?= $i['id'] ?>"><?= View::e($i['invoice_number']) ?></a></td>
          <td><span class="badge badge-<?= $i['status']==='paid'?'green':'yellow' ?>"><?= View::e($i['status']) ?></span></td>
          <td><?= View::money((float)$i['total']) ?></td>
          <td class="help-text"><?= View::e($i['due_date']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
