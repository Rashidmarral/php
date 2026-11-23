<?php

use App\Core\Lang;
use App\Core\Auth;
use App\Core\Settings;
use App\Core\View;
use App\Models\Page;

$otherLang = Lang::locale() === 'ar' ? 'en' : 'ar';
$otherLangLabel = Lang::locale() === 'ar' ? 'EN' : 'AR';
$platformLogo = Settings::get('platform_logo_path', '');
$platformCr = Settings::get('platform_cr_number', '');
$platformVat = Settings::get('platform_vat_number', '');
$platformLegalName = Lang::locale() === 'ar' ? (Settings::get('platform_legal_name_ar', '') ?: Settings::get('platform_legal_name_en', 'BuildXact Saudi')) : Settings::get('platform_legal_name_en', 'BuildXact Saudi');
$isAr = Lang::locale() === 'ar';
$headerPhone = Settings::get('header_phone', '');
$footerTagline = ($isAr ? Settings::get('footer_tagline_ar', '') : Settings::get('footer_tagline_en', '')) ?: t('footer.tagline');
$footerCities = ($isAr ? Settings::get('footer_cities_ar', '') : Settings::get('footer_cities_en', '')) ?: 'Riyadh · Jeddah · Dammam';
$footerBottomNote = $isAr ? Settings::get('footer_bottom_note_ar', '') : Settings::get('footer_bottom_note_en', '');
$socialLinks = [
    'Facebook' => Settings::get('social_facebook_url', ''),
    'X' => Settings::get('social_twitter_url', ''),
    'Instagram' => Settings::get('social_instagram_url', ''),
    'LinkedIn' => Settings::get('social_linkedin_url', ''),
    'WhatsApp' => Settings::get('social_whatsapp_url', ''),
];
$navPages = [];
$footerPages = [];
try {
    $navPages = Page::forNav();
    $footerPages = Page::forFooter();
} catch (\Throwable $e) {
    // pages table may not exist yet on a not-yet-migrated install
}
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
      <?php foreach ($navPages as $np): ?>
        <a href="/p/<?= View::e($np['slug']) ?>"><?= View::e(($isAr ? ($np['nav_label_ar'] ?: $np['title_ar']) : ($np['nav_label_en'] ?: $np['title_en']))) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="header-actions">
      <?php if ($headerPhone): ?><a class="lang-switch" href="tel:<?= View::e(preg_replace('/\s+/', '', $headerPhone)) ?>" style="direction:ltr;"><?= View::e($headerPhone) ?></a><?php endif; ?>
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
        <p style="color:#a9c4bd;font-size:13.5px;margin-top:10px;max-width:280px;"><?= View::e($footerTagline) ?></p>
        <?php if (array_filter($socialLinks)): ?>
          <div style="display:flex;gap:10px;margin-top:14px;">
            <?php foreach ($socialLinks as $label => $url): if (!$url): continue; endif; ?>
              <a href="<?= View::e($url) ?>" target="_blank" rel="noopener" title="<?= View::e($label) ?>" style="color:#a9c4bd;font-size:12.5px;border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:4px 8px;"><?= View::e($label) ?></a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
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
          <?php foreach ($footerPages as $fp): ?>
            <li><a href="/p/<?= View::e($fp['slug']) ?>"><?= View::e(($isAr ? ($fp['nav_label_ar'] ?: $fp['title_ar']) : ($fp['nav_label_en'] ?: $fp['title_en']))) ?></a></li>
          <?php endforeach; ?>
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
      <?php endif; ?><?php if ($footerBottomNote): ?> · <?= View::e($footerBottomNote) ?><?php endif; ?></span>
      <span><?= View::e($footerCities) ?></span>
    </div>
  </div>
</footer>
<script src="/assets/js/site.js" defer></script>
</body>
</html>
