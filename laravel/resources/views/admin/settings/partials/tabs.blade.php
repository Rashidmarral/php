<?php
/** @var string $active */
?>
<div class="quick-links" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;">
  <a href="/admin/settings/payments" class="btn btn-light btn-sm">💳 <?= t('admin.settings.quick_payment_gateways') ?></a>
  <a href="/admin/certificates" class="btn btn-light btn-sm">🏅 <?= t('admin.settings.quick_certificates') ?></a>
  <a href="/admin/settings#currency" class="btn btn-light btn-sm">💱 <?= t('admin.settings.quick_currencies') ?></a>
  <a href="/admin/backups" class="btn btn-light btn-sm">🗄️ <?= t('admin.settings.quick_backups') ?></a>
</div>

<div class="tabs">
  <a href="/admin/settings"<?= $active === 'general' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/identity"<?= $active === 'identity' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_identity') ?></a>
  <a href="/admin/settings/branding"<?= $active === 'branding' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_branding') ?></a>
  <a href="/admin/settings/signup"<?= $active === 'signup' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_signup') ?></a>
  <a href="/admin/settings/maintenance"<?= $active === 'maintenance' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_maintenance') ?></a>
  <a href="/admin/settings/features"<?= $active === 'features' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_features') ?></a>
  <a href="/admin/settings/email"<?= $active === 'email' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_email') ?></a>
  <a href="/admin/settings/storage"<?= $active === 'storage' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_storage') ?></a>
  <a href="/admin/settings/system"<?= $active === 'system' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_system') ?></a>
  <a href="/admin/settings/header"<?= $active === 'header' ? ' class="active"' : '' ?>><?= t('admin.settings.tab_header') ?></a>
</div>
