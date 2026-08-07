<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Expert Consultations</h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">Requests from companies for a live consultation with an engineer — assign someone and confirm a time.</p>

<div class="tabs" style="margin-bottom:20px;">
  <a href="/admin/consultations" class="<?= $statusFilter === '' ? 'active' : '' ?>">All</a>
  <?php foreach (\App\Models\Consultation::STATUSES as $s): ?>
    <a href="/admin/consultations?status=<?= $s ?>" class="<?= $statusFilter === $s ? 'active' : '' ?>"><?= ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<table class="data">
  <thead><tr><th>Requested</th><th>Company</th><th>Requested by</th><th>Format</th><th>Topic</th><th>Status</th><th>Engineer</th><th>Scheduled</th><?php if (Auth::isSuperAdmin()): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($consultations as $c): $fid = 'con-' . $c['id']; ?>
    <?php if (Auth::isSuperAdmin()): ?>
      <form id="<?= $fid ?>" method="post" action="/admin/consultations/<?= $c['id'] ?>/update"><?= Csrf::field() ?></form>
    <?php endif; ?>
    <tr>
      <td class="help-text" style="white-space:nowrap;"><?= View::e($c['created_at']) ?></td>
      <td><a href="/admin/companies/<?= $c['company_id'] ?>"><?= View::e($c['company_name']) ?></a></td>
      <td><?= View::e($c['requested_by_name'] ?? '—') ?></td>
      <td><?= $c['type'] === 'in_person' ? '🚗 In-person' : '💬 Chat/video' ?></td>
      <td style="max-width:220px;"><?= View::e($c['topic']) ?><?php if ($c['notes']): ?><div class="help-text"><?= View::e($c['notes']) ?></div><?php endif; ?></td>
      <td>
        <?php if (Auth::isSuperAdmin()): ?>
          <select form="<?= $fid ?>" name="status" style="width:120px;">
            <?php foreach (\App\Models\Consultation::STATUSES as $s): ?>
              <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="badge badge-gray"><?= ucfirst($c['status']) ?></span>
        <?php endif; ?>
      </td>
      <td><?php if (Auth::isSuperAdmin()): ?><input form="<?= $fid ?>" type="text" name="assigned_engineer" value="<?= View::e($c['assigned_engineer'] ?? '') ?>" style="width:130px;" placeholder="Engineer name"><?php else: ?><?= View::e($c['assigned_engineer'] ?: '—') ?><?php endif; ?></td>
      <td><?php if (Auth::isSuperAdmin()): ?><input form="<?= $fid ?>" type="datetime-local" name="scheduled_at" value="<?= View::e($c['scheduled_at'] ?? '') ?>" style="width:170px;"><?php else: ?><?= View::e($c['scheduled_at'] ?: '—') ?><?php endif; ?></td>
      <?php if (Auth::isSuperAdmin()): ?>
        <td><button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($consultations)): ?>
    <tr><td colspan="9" class="help-text" style="text-align:center;padding:20px;">No consultation requests yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
