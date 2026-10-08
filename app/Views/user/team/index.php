<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1><?= t('user.team.title') ?></h1>
  <?php if ($userLimit !== null && $userLimit < 999): ?>
    <span class="badge badge-<?= $withinUserLimit ? 'gray' : 'red' ?>"><?= count($members) ?> / <?= $userLimit ?> <?= t('user.team.members_suffix') ?></span>
  <?php endif; ?>
</div>

<?php if (Auth::can('manage_team')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.team.invite_member') ?></h3>
  <?php if (!$withinUserLimit): ?>
    <div class="alert alert-error"><?= t('user.team.limit_reached', ['limit' => $userLimit]) ?> <a href="/app/billing"><?= t('user.team.upgrade_plan') ?></a></div>
  <?php else: ?>
  <form method="post" action="/app/team" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name') ?></label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.email') ?></label><input type="email" name="email" required></div>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.role') ?></label>
      <select name="role">
        <?php foreach (Auth::ASSIGNABLE_ROLES as $key => $label): ?>
          <option value="<?= View::e($key) ?>" <?= $key === 'estimator' ? 'selected' : '' ?>><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.invite') ?></button>
  </form>
  <p class="help-text" style="margin-top:10px;margin-bottom:0;">
    <?= t('user.team.roles_hint') ?>
  </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<table class="data">
  <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.email') ?></th><th><?= t('common.role') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $fid = 'role-' . $m['id']; ?>
    <?php if (Auth::can('manage_team') && $m['role'] !== 'owner'): ?>
      <form id="<?= $fid ?>" method="post" action="/app/team/<?= $m['id'] ?>/role"><?= Csrf::field() ?></form>
    <?php endif; ?>
    <tr>
      <td><?= View::e($m['name']) ?><?php if ((int)$m['id'] === (int)Auth::user()['id']): ?> <span class="help-text"><?= t('user.team.you') ?></span><?php endif; ?></td>
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
        <form method="post" action="/app/team/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= t('user.team.remove_member_confirm') ?>');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-light"><?= t('common.remove') ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
