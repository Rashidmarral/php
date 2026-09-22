<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1><?= t('admin.users.title') ?></h1>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('admin.users.add') ?></h3>
  <form method="post" action="/admin/admins" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr 1fr auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name') ?></label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.email') ?></label><input type="email" name="email" required></div>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.password') ?></label>
      <div class="password-field">
        <input type="password" name="password" required minlength="8">
        <?= View::passwordToggle() ?>
      </div>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.role') ?></label>
      <select name="role">
        <?php foreach (Auth::ADMIN_ROLES as $val => $label): ?>
          <option value="<?= $val ?>"><?= View::e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('admin.users.add_btn') ?></button>
  </form>
</div>

<table class="data">
  <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.email') ?></th><th><?= t('common.role') ?></th><th><?= t('common.joined') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($admins as $a): ?>
    <tr>
      <td><?= View::e($a['name']) ?></td>
      <td><?= View::e($a['email']) ?></td>
      <td><span class="badge badge-<?= $a['role'] === 'super_admin' ? 'blue' : 'gray' ?>"><?= $a['role'] === 'super_admin' ? t('admin.users.super_admin') : t('admin.users.support_admin') ?></span></td>
      <td class="help-text"><?= View::e($a['created_at']) ?></td>
      <td>
        <?php if ((int)$a['id'] !== (int)Auth::user()['id']): ?>
        <form method="post" action="/admin/admins/<?= $a['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.users.remove_confirm') ?>');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-light"><?= t('common.remove') ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
