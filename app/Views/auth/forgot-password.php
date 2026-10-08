<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Reset your password</h2>
    <p style="color:var(--muted);margin-bottom:20px;">Enter the email address on your account and we'll send you a link to reset your password.</p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/forgot-password">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
    </form>
    <p style="margin-top:16px;font-size:14px;"><a href="/login">← Back to login</a></p>
  </div>
</div>
