<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($project['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Client: <?= View::e($client['name'] ?? '—') ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/projects/<?= $project['id'] ?>/edit" class="btn btn-light">Edit</a>
    <form method="post" action="/app/projects/<?= $project['id'] ?>/delete" onsubmit="return confirm('Delete this project?');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-danger">Delete</button>
    </form>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$project['status'])) ?></span></div></div>
  <div class="kpi"><div class="label">Original budget</div><div class="value"><?= View::money((float)$project['budget']) ?></div></div>
  <div class="kpi"><div class="label">Revised budget</div><div class="value"><?= View::money((float)$project['budget'] + $approvedChangeOrdersTotal) ?></div></div>
  <div class="kpi"><div class="label">Start / End</div><div class="value" style="font-size:16px;"><?= View::e($project['start_date'] ?: '—') ?> → <?= View::e($project['end_date'] ?: '—') ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Estimates</h3>
    <?php if (empty($estimates)): ?><p class="help-text">No estimates linked to this project.</p><?php else: ?>
      <table class="data"><thead><tr><th>Title</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($estimates as $e): ?>
        <tr><td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e($e['title']) ?></a></td><td><span class="badge badge-gray"><?= View::e($e['status']) ?></span></td><td><?= View::money((float)$e['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3>Invoices</h3>
    <?php if (empty($invoices)): ?><p class="help-text">No invoices linked to this project.</p><?php else: ?>
      <table class="data"><thead><tr><th>#</th><th>Status</th><th>Total</th></tr></thead><tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr><td><a href="/app/invoices/<?= $inv['id'] ?>"><?= View::e($inv['invoice_number']) ?></a></td><td><span class="badge badge-<?= $inv['status']==='paid'?'green':'yellow' ?>"><?= View::e($inv['status']) ?></span></td><td><?= View::money((float)$inv['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Change orders (variation orders)</h3>
  <p class="help-text" style="margin-top:-6px;">Track scope changes after the original contract — approving one adjusts the project's revised budget above. Use a negative amount for a scope reduction.</p>

  <?php if (!empty($changeOrders)): ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th>Title</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($changeOrders as $co): ?>
        <tr>
          <td><?= View::e($co['title']) ?><?php if ($co['description']): ?><br><span class="help-text"><?= View::e($co['description']) ?></span><?php endif; ?></td>
          <td><?= (float)$co['amount'] >= 0 ? '+' : '' ?><?= View::money((float)$co['amount']) ?></td>
          <td><span class="badge badge-<?= $co['status']==='approved'?'green':($co['status']==='rejected'?'red':'yellow') ?>"><?= View::e(ucfirst($co['status'])) ?></span></td>
          <td style="display:flex;gap:6px;">
            <?php if ($co['status'] === 'pending'): ?>
              <form method="post" action="/app/change-orders/<?= $co['id'] ?>/status" style="display:inline;">
                <?= Csrf::field() ?><input type="hidden" name="status" value="approved">
                <button type="submit" class="btn btn-sm btn-primary">Approve</button>
              </form>
              <form method="post" action="/app/change-orders/<?= $co['id'] ?>/status" style="display:inline;">
                <?= Csrf::field() ?><input type="hidden" name="status" value="rejected">
                <button type="submit" class="btn btn-sm btn-light">Reject</button>
              </form>
            <?php endif; ?>
            <form method="post" action="/app/change-orders/<?= $co['id'] ?>/delete" onsubmit="return confirm('Remove this change order?');" style="display:inline;">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/change-orders" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label>Title</label><input type="text" name="title" placeholder="e.g. Additional glazing" required></div>
    <div class="form-group" style="margin:0;width:160px;"><label>Amount (SAR)</label><input type="number" step="0.01" name="amount" placeholder="e.g. 15000 or -5000" required></div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label>Description</label><input type="text" name="description"></div>
    <button type="submit" class="btn btn-outline">+ Add change order</button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Site photo diary</h3>
  <p class="help-text" style="margin-top:-6px;">Keep a dated record of site progress — cheap to check, hard to argue with.</p>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/photos" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <div class="form-group" style="margin:0;"><label>Date</label><input type="date" name="taken_on" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label>Caption</label><input type="text" name="caption" placeholder="e.g. Foundation poured, north wing"></div>
    <button type="submit" class="btn btn-outline">+ Add photo</button>
  </form>

  <?php if (empty($photos)): ?>
    <p class="help-text">No site photos yet.</p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">
      <?php foreach ($photos as $photo): ?>
        <div>
          <a href="<?= View::e($photo['file_path']) ?>" target="_blank">
            <img src="<?= View::e($photo['file_path']) ?>" alt="Site photo" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
          </a>
          <p class="help-text" style="margin-top:4px;margin-bottom:0;"><?= View::e($photo['taken_on'] ?: '') ?></p>
          <?php if ($photo['caption']): ?><p style="font-size:12.5px;margin:2px 0 4px;"><?= View::e($photo['caption']) ?></p><?php endif; ?>
          <form method="post" action="/app/project-photos/<?= $photo['id'] ?>/delete" onsubmit="return confirm('Remove this photo?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light" style="width:100%;">Remove</button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Schedule</h3>
  <?php if (empty($tasks)): ?><p class="help-text">No scheduled tasks for this project yet. Add some from the <a href="/app/schedule">Schedule</a> page.</p><?php else: ?>
    <table class="data"><thead><tr><th>Task</th><th>Start</th><th>End</th><th>Status</th></tr></thead><tbody>
    <?php foreach ($tasks as $tk): ?>
      <tr><td><?= View::e($tk['title']) ?></td><td><?= View::e($tk['start_date']) ?></td><td><?= View::e($tk['end_date']) ?></td><td><span class="badge badge-gray"><?= View::e(str_replace('_',' ',$tk['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>
