<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>My Profile</h1>
</div>

<form method="post" action="/admin/profile" class="card" style="max-width:520px;">
  <?= Csrf::field() ?>
  <h3 style="font-size:14px;">Account details</h3>
  <div class="form-group"><label>Name</label><input type="text" name="name" value="<?= View::e($admin['name']) ?>" required></div>
  <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($admin['email']) ?>" required></div>

  <h3 style="font-size:14px;margin-top:20px;">Change password</h3>
  <p class="help-text" style="margin-top:-8px;">Leave blank to keep your current password.</p>
  <div class="form-group">
    <label>Current password</label>
    <div class="password-field">
      <input type="password" name="current_password" autocomplete="current-password">
      <?= View::passwordToggle() ?>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>New password</label>
      <div class="password-field">
        <input type="password" name="new_password" autocomplete="new-password" minlength="8">
        <?= View::passwordToggle() ?>
      </div>
    </div>
    <div class="form-group">
      <label>Confirm new password</label>
      <div class="password-field">
        <input type="password" name="new_password_confirm" autocomplete="new-password" minlength="8">
        <?= View::passwordToggle() ?>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:8px;">Save changes</button>
</form>
