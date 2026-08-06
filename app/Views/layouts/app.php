<?php

use App\Core\Lang;
use App\Core\Auth;
use App\Core\Feature;

$user = Auth::user();
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isActive = fn(string $p) => str_starts_with($path, $p) ? 'active' : '';
$otherLang = Lang::locale() === 'ar' ? 'en' : 'ar';
$otherLangLabel = Lang::locale() === 'ar' ? 'EN' : 'AR';
$navLink = function (string $href, string $icon, string $label, ?string $featureKey = null) use ($isActive) {
    $locked = $featureKey !== null && !Feature::allows($featureKey);
    $target = $locked ? '/app/billing' : $href;
    $activeClass = !$locked ? $isActive($href) : '';
    echo '<a href="' . $target . '" class="' . $activeClass . ($locked ? ' locked' : '') . '">' . $icon . ' ' . $label . ($locked ? ' <span class="lock">🔒</span>' : '') . '</a>';
};
?><!doctype html>
<html lang="<?= Lang::locale() ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? \App\Core\View::e($pageTitle) . ' · ' : '' ?>BuildXact Saudi</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <aside class="sidebar">
    <div class="brand"><span class="mark" style="background:#fff;color:var(--brand-dark)">BX</span> BuildXact</div>
    <nav>
      <a href="/app" class="<?= $isActive('/app') === 'active' && $path === '/app' ? 'active' : '' ?>">📊 <?= t('side.dashboard') ?></a>
      <a href="/app/projects" class="<?= $isActive('/app/projects') ?>">🏗️ <?= t('side.projects') ?></a>
      <a href="/app/quick-estimate" class="<?= $isActive('/app/quick-estimate') ?>">⚡ <?= t('side.quick_estimate') ?></a>
      <a href="/app/estimates" class="<?= $isActive('/app/estimates') ?>">🧾 <?= t('side.estimates') ?></a>
      <a href="/app/invoices" class="<?= $isActive('/app/invoices') ?>">💳 <?= t('side.invoices') ?></a>
      <a href="/app/clients" class="<?= $isActive('/app/clients') ?>">👥 <?= t('side.clients') ?></a>
      <a href="/app/schedule" class="<?= $isActive('/app/schedule') ?>">📅 <?= t('side.schedule') ?></a>
      <?php $navLink('/app/takeoffs', '📐', t('side.takeoffs'), 'takeoff'); ?>

      <div class="nav-section">Resources</div>
      <?php $navLink('/app/suppliers', '🚚', t('side.suppliers'), 'suppliers'); ?>
      <?php $navLink('/app/materials', '📦', t('side.materials'), 'materials'); ?>
      <?php $navLink('/app/documents', '📁', t('side.documents'), 'documents'); ?>

      <div class="nav-section">Insights</div>
      <?php $navLink('/app/reports', '📈', t('side.reports'), 'reports'); ?>

      <div class="nav-section">Company</div>
      <a href="/app/team" class="<?= $isActive('/app/team') ?>">🧑‍💼 <?= t('side.team') ?></a>
      <a href="/app/billing" class="<?= $isActive('/app/billing') ?>">💰 <?= t('side.billing') ?></a>
      <?php $navLink('/app/integrations', '🔌', t('side.integrations'), 'integrations'); ?>
      <a href="/app/settings" class="<?= $isActive('/app/settings') ?>">⚙️ <?= t('side.settings') ?></a>
    </nav>
    <div class="foot">
      <a href="/" style="color:#a9c4bd">← <?= t('side.back_site') ?></a>
    </div>
  </aside>
  <div class="main">
    <div class="topbar">
      <div class="who"><?= \App\Core\View::e($user['name'] ?? '') ?> · <span class="badge badge-blue"><?= \App\Core\View::e(ucfirst($user['role'] ?? '')) ?></span></div>
      <div class="header-actions">
        <a class="lang-switch" href="?lang=<?= $otherLang ?>"><?= $otherLangLabel ?></a>
        <form method="post" action="/logout" style="margin:0">
          <?= \App\Core\Csrf::field() ?>
          <button type="submit" class="btn btn-light btn-sm"><?= t('side.logout') ?></button>
        </form>
      </div>
    </div>
    <div class="content">
      <?php if (!empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
          <?php foreach ($messages as $m): ?>
            <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= \App\Core\View::e($m) ?></div>
          <?php endforeach; ?>
        <?php endforeach; unset($_SESSION['flash']); endif; ?>
      <?= $content ?>
    </div>
  </div>
</div>
</body>
</html>
