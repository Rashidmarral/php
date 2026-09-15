<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.profile.title') ?></h1>
</div>

<form method="post" action="/admin/profile" class="card" style="max-width:520px;">
  <?= Csrf::field() ?>
  <h3 style="font-size:14px;"><?= t('admin.profile.account_details') ?></h3>
  <div class="form-group"><label><?= t('common.name') ?></label><input type="text" name="name" value="<?= View::e($admin['name']) ?>" required></div>
  <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= View::e($admin['email']) ?>" required></div>

  <h3 style="font-size:14px;margin-top:20px;"><?= t('admin.profile.change_password') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('admin.profile.change_password_hint') ?></p>
  <div class="form-group">
    <label><?= t('common.current_password') ?></label>
    <div class="password-field">
      <input type="password" name="current_password" autocomplete="current-password">
      <?= View::passwordToggle() ?>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.new_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password" autocomplete="new-password" minlength="8">
        <?= View::passwordToggle() ?>
      </div>
    </div>
    <div class="form-group">
      <label><?= t('common.confirm_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8">
        <?= View::passwordToggle() ?>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:8px;"><?= t('common.save_changes') ?></button>
</form>
