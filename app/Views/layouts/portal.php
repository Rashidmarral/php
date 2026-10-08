<?php

use App\Core\Lang;
use App\Core\Csrf;
use App\Core\View;

$otherLang = Lang::locale() === 'ar' ? 'en' : 'ar';
$otherLangLabel = Lang::locale() === 'ar' ? 'EN' : 'AR';
?><!doctype html>
<html lang="<?= Lang::locale() ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? View::e($pageTitle) . ' · ' : '' ?>Client Portal</title>
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="/portal" class="logo"><span class="mark">BX</span> <?= View::e($company['name'] ?? 'Client Portal') ?></a>
    <div class="header-actions">
      <a class="lang-switch" href="?lang=<?= $otherLang ?>"><?= $otherLangLabel ?></a>
      <span class="help-text"><?= View::e($client['name'] ?? '') ?></span>
      <form method="post" action="/portal/logout" style="margin:0">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-light btn-sm">Log out</button>
      </form>
    </div>
  </div>
</header>
<div class="container" style="padding:32px 24px;">
  <?php if (!empty($_SESSION['flash'])): ?>
    <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
      <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
    <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>
  <?= $content ?>
</div>
</body>
</html>
