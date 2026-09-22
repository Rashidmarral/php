<?php
/** @var string $active */
?>
<div class="tabs">
  <a href="/app/settings"<?= $active === 'profile' ? ' class="active"' : '' ?>><?= t('user.settings.tab_profile') ?></a>
  <a href="/app/settings/legal"<?= $active === 'legal' ? ' class="active"' : '' ?>><?= t('user.settings.tab_legal') ?></a>
  <a href="/app/settings/business"<?= $active === 'business' ? ' class="active"' : '' ?>><?= t('user.settings.tab_business') ?></a>
  <a href="/app/settings/invoice-templates/invoice"<?= $active === 'invoice_templates' ? ' class="active"' : '' ?>><?= t('user.settings.tab_invoice_templates') ?></a>
  <a href="/app/settings/security"<?= $active === 'security' ? ' class="active"' : '' ?>><?= t('user.settings.tab_security') ?></a>
</div>
