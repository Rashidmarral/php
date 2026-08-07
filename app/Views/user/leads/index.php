<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Leads</h1>
  <a href="/app/leads/create" class="btn btn-primary">+ New Lead</a>
</div>

<div class="toolbar" style="margin-bottom:20px;">
  <a href="/app/leads" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-light' ?>">All (<?= array_sum($counts) ?>)</a>
  <?php foreach ($statuses as $s): ?>
    <a href="/app/leads?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-light' ?>"><?= ucfirst($s) ?> (<?= $counts[$s] ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (empty($leads)): ?>
  <div class="empty-state card">
    <div class="icon">🎯</div>
    <p>No leads yet. Quick Estimate submissions from your public estimator can also land here — <a href="/app/leads/create">add your first lead</a>.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Contact</th><th>Source</th><th>Est. value</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
      <tr>
        <td><a href="/app/leads/<?= $l['id'] ?>/edit"><?= View::e($l['name']) ?></a><?php if ($l['company_name']): ?><br><span class="help-text"><?= View::e($l['company_name']) ?></span><?php endif; ?></td>
        <td><?= View::e($l['email'] ?: '—') ?><?php if ($l['phone']): ?><br><span class="help-text"><bdi><?= View::e($l['phone']) ?></bdi></span><?php endif; ?></td>
        <td><span class="badge badge-gray"><?= View::e(ucwords(str_replace('_', ' ', $l['source']))) ?></span></td>
        <td><?= View::money((float)$l['estimated_value']) ?></td>
        <td>
          <form method="post" action="/app/leads/<?= $l['id'] ?>/status" style="display:inline;">
            <?= Csrf::field() ?>
            <select name="status" onchange="this.form.requestSubmit()" style="width:auto;display:inline-block;padding:4px 8px;font-size:12.5px;">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td style="display:flex;gap:6px;">
          <?php if (empty($l['converted_client_id']) && $l['status'] !== 'lost'): ?>
            <form method="post" action="/app/leads/<?= $l['id'] ?>/convert" onsubmit="return confirm('Convert this lead to a client?');" style="display:inline;">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-outline">→ Client</button>
            </form>
          <?php elseif ($l['converted_client_id']): ?>
            <a href="/app/clients/<?= $l['converted_client_id'] ?>/edit" class="btn btn-sm btn-light">View client</a>
          <?php endif; ?>
          <form method="post" action="/app/leads/<?= $l['id'] ?>/delete" onsubmit="return confirm('Delete this lead?');" style="display:inline;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
