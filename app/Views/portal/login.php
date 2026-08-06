<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="auth-wrap">
  <div class="auth-card">
    <h2>Client Portal</h2>
    <p style="color:var(--muted);margin-bottom:20px;">Log in to view your projects, estimates, and invoices.</p>

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <form method="post" action="/portal/login">
      <?= Csrf::field() ?>
      <div class="form-group"><label>Email address</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>
    <p class="help-text" style="margin-top:14px;">Your contractor gives you access to the client portal — contact them if you don't have a login yet.</p>
  </div>
</div>
