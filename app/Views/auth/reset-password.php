<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Set a new password</h2>
    <p style="color:var(--muted);margin-bottom:20px;">Choose a new password for your account.</p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/reset-password/<?= View::e($token) ?>">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label>New password</label>
        <div class="password-field">
          <input type="password" name="password" required minlength="8" autofocus>
          <?= View::passwordToggle() ?>
        </div>
      </div>
      <div class="form-group">
        <label>Confirm new password</label>
        <div class="password-field">
          <input type="password" name="password_confirm" required minlength="8">
          <?= View::passwordToggle() ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Reset password</button>
    </form>
  </div>
</div>
