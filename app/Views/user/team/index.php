<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Team</h1>
  <?php if ($userLimit !== null && $userLimit < 999): ?>
    <span class="badge badge-<?= $withinUserLimit ? 'gray' : 'red' ?>"><?= count($members) ?> / <?= $userLimit ?> members</span>
  <?php endif; ?>
</div>

<?php if (Auth::can('manage_team')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3>Invite a team member</h3>
  <?php if (!$withinUserLimit): ?>
    <div class="alert alert-error">Your plan's team member limit (<?= $userLimit ?>) has been reached. <a href="/app/billing">Upgrade your plan</a> to invite more.</div>
  <?php else: ?>
  <form method="post" action="/app/team" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Name</label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label>Email</label><input type="email" name="email" required></div>
    <div class="form-group" style="margin:0;">
      <label>Role</label>
      <select name="role">
        <?php foreach (Auth::ASSIGNABLE_ROLES as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= $key === 'estimator' ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Invite</button>
  </form>
  <p class="help-text" style="margin-top:10px;margin-bottom:0;">
    <strong>Admin</strong> — everything except billing. <strong>Estimator</strong> — projects, clients, estimates & schedule.
    <strong>Accountant</strong> — invoices, payments & reports. <strong>Viewer</strong> — read-only.
  </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<table class="data">
  <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $fid = 'role-' . $m['id']; ?>
    <?php if (Auth::can('manage_team') && $m['role'] !== 'owner'): ?>
      <form id="<?= $fid ?>" method="post" action="/app/team/<?= $m['id'] ?>/role"><?= Csrf::field() ?></form>
    <?php endif; ?>
    <tr>
      <td><?= View::e($m['name']) ?><?php if ((int)$m['id'] === (int)Auth::user()['id']): ?> <span class="help-text">(you)</span><?php endif; ?></td>
      <td><?= View::e($m['email']) ?></td>
      <td>
        <?php if (Auth::can('manage_team') && $m['role'] !== 'owner'): ?>
          <select form="<?= $fid ?>" name="role" onchange="this.form.requestSubmit()" style="width:auto;display:inline-block;padding:4px 8px;font-size:12.5px;">
            <?php foreach (Auth::ASSIGNABLE_ROLES as $key => $label): ?>
              <option value="<?= View::e($key) ?>" <?= $m['role'] === $key ? 'selected' : '' ?>><?= View::e(Auth::ROLE_LABELS[$key] ?? ucfirst($key)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="badge badge-blue"><?= View::e(Auth::ROLE_LABELS[$m['role']] ?? ucfirst($m['role'])) ?></span>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-green"><?= View::e($m['status']) ?></span></td>
      <td>
        <?php if (Auth::can('manage_team') && (int)$m['id'] !== (int)Auth::user()['id'] && $m['role'] !== 'owner'): ?>
        <form method="post" action="/app/team/<?= $m['id'] ?>/delete" onsubmit="return confirm('Remove this team member?');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-light">Remove</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
