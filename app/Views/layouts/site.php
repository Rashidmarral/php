<?php

use App\Core\Lang;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\View;

$otherLang = Lang::locale() === 'ar' ? 'en' : 'ar';
$otherLangLabel = Lang::locale() === 'ar' ? 'EN' : 'AR';
$platformLogo = Settings::get('platform_logo_path', '');
$platformCr = Settings::get('platform_cr_number', '');
$platformVat = Settings::get('platform_vat_number', '');
$platformLegalName = Lang::locale() === 'ar' ? (Settings::get('platform_legal_name_ar', '') ?: Settings::get('platform_legal_name_en', 'BuildXact Saudi')) : Settings::get('platform_legal_name_en', 'BuildXact Saudi');
?><!doctype html>
<html lang="<?= Lang::locale() ?>" dir="<?= Lang::dir() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? \App\Core\View::e($pageTitle) . ' · ' : '' ?>BuildXact Saudi</title>
<meta name="description" content="Construction management and job costing software for Saudi Arabia's contractors, builders and developers.">
<link rel="stylesheet" href="/assets/css/app.css">
<script>document.documentElement.classList.add('js');</script>
</head>
<body>
<header class="site-header">
  <div class="container">
    <a href="/" class="logo">
      <?php if ($platformLogo): ?>
        <img src="<?= View::e($platformLogo) ?>" alt="Logo" style="height:32px;border-radius:6px;">
      <?php else: ?>
        <span class="mark">BX</span>
      <?php endif; ?>
      BuildXact <span style="color:#d4a017">السعودية</span>
    </a>
    <nav class="nav-links">
      <a href="/features"><?= t('nav.features') ?></a>
      <a href="/pricing"><?= t('nav.pricing') ?></a>
      <a href="/quick-estimate"><?= t('nav.quick_estimate') ?></a>
      <a href="/about"><?= t('nav.about') ?></a>
      <a href="/contact"><?= t('nav.contact') ?></a>
    </nav>
    <div class="header-actions">
      <a class="lang-switch" href="?lang=<?= $otherLang ?>"><?= $otherLangLabel ?></a>
      <?php if (Auth::check()): ?>
        <a class="btn btn-outline btn-sm" href="<?= Auth::isSuperAdmin() ? '/admin' : '/app' ?>"><?= t('nav.dashboard') ?></a>
      <?php else: ?>
        <a class="btn btn-light btn-sm" href="/login"><?= t('nav.login') ?></a>
        <a class="btn btn-primary btn-sm" href="/register"><?= t('nav.start_trial') ?></a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?= $content ?>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div>
        <div class="logo" style="color:#fff"><span class="mark">BX</span> BuildXact Saudi</div>
        <p style="color:#a9c4bd;font-size:13.5px;margin-top:10px;max-width:280px;"><?= t('footer.tagline') ?></p>
      </div>
      <div>
        <h4><?= t('footer.product') ?></h4>
        <ul>
          <li><a href="/features"><?= t('nav.features') ?></a></li>
          <li><a href="/pricing"><?= t('nav.pricing') ?></a></li>
          <li><a href="/login"><?= t('nav.login') ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= t('footer.company') ?></h4>
        <ul>
          <li><a href="/about"><?= t('nav.about') ?></a></li>
          <li><a href="/contact"><?= t('nav.contact') ?></a></li>
        </ul>
      </div>
      <div>
        <h4><?= t('footer.legal') ?></h4>
        <ul>
          <li><a href="/privacy"><?= t('footer.privacy') ?></a></li>
          <li><a href="/terms"><?= t('footer.terms') ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= View::e($platformLegalName) ?>. <?= t('footer.rights') ?><?php if ($platformCr || $platformVat): ?>
        <?php if ($platformCr): ?> · <?= t('footer.cr') ?>: <bdi><?= View::e($platformCr) ?></bdi><?php endif; ?>
        <?php if ($platformVat): ?> · <?= t('footer.vat') ?>: <bdi><?= View::e($platformVat) ?></bdi><?php endif; ?>
      <?php endif; ?></span>
      <span>Riyadh · Jeddah · Dammam</span>
    </div>
  </div>
</footer>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
