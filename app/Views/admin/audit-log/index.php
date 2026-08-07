<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Audit Log</h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">A record of admin actions on the platform — who changed what, and when. Showing the most recent 300 entries.</p>

<form method="get" action="/admin/audit-log" class="card" style="margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label>Admin</label>
    <input type="text" name="admin" value="<?= View::e($adminFilter) ?>" placeholder="Name contains…">
  </div>
  <div class="form-group" style="margin:0;">
    <label>Action</label>
    <select name="action">
      <option value="">All actions</option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= View::e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= View::e(str_replace('_', ' ', $a)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary">Filter</button>
  <?php if ($adminFilter !== '' || $actionFilter !== ''): ?>
    <a href="/admin/audit-log" class="btn btn-light">Clear</a>
  <?php endif; ?>
</form>

<table class="data">
  <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="help-text" style="white-space:nowrap;"><?= View::e($l['created_at']) ?></td>
      <td><?= View::e($l['admin_name'] ?: '—') ?></td>
      <td><span class="badge badge-gray"><?= View::e(str_replace('_', ' ', $l['action'])) ?></span></td>
      <td class="help-text"><?= View::e($l['target_type'] ? ($l['target_type'] . ' #' . $l['target_id']) : '—') ?></td>
      <td><?= View::e($l['details'] ?? '') ?></td>
      <td class="help-text"><?= View::e($l['ip_address'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($logs)): ?>
    <tr><td colspan="6" class="help-text" style="text-align:center;padding:20px;">No matching activity yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
