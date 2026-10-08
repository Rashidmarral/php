<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2><?= t('auth.login_title') ?></h2>
    <p style="color:var(--muted);margin-bottom:20px;"><?= t('auth.login_sub') ?></p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/login">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label><?= t('auth.email') ?></label>
        <input type="email" name="email" required value="<?= View::old('email') ?>">
      </div>
      <div class="form-group">
        <label><?= t('auth.password') ?></label>
        <div class="password-field">
          <input type="password" name="password" required>
          <?= View::passwordToggle() ?>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-block"><?= t('auth.login_btn') ?></button>
    </form>
    <p style="margin-top:14px;font-size:13.5px;"><a href="/forgot-password"><?= t('auth.forgot_password') ?></a></p>
    <p style="margin-top:2px;font-size:14px;"><?= t('auth.no_account') ?> <a href="/register"><?= t('auth.register_link') ?></a></p>
    <p class="help-text" style="margin-top:14px;border-top:1px solid var(--border);padding-top:12px;"><?= t('auth.demo_hint') ?></p>
  </div>
</div>
