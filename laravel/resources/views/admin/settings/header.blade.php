@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/settings"><?= t('admin.settings.tab_general') ?></a>
  <a href="/admin/settings/payments"><?= t('admin.settings.tab_payments') ?></a>
  <a href="/admin/settings/legal"><?= t('admin.settings.tab_legal') ?></a>
  <a href="/admin/settings/header" class="active"><?= t('admin.settings.tab_header') ?></a>
  <a href="/admin/settings/ai"><?= t('admin.settings.tab_ai') ?></a>
  <a href="/admin/settings/notifications"><?= t('admin.settings.tab_notifications') ?></a>
  <a href="/admin/settings/email"><?= t('admin.settings.tab_email') ?></a>
</div>

<p class="help-text" style="margin-top:-8px;margin-bottom:20px;max-width:680px;">
  Leave any field blank to keep the site's default wording. Manage which custom pages appear
  in the nav/footer from <a href="/admin/pages">Website Pages</a>, edit any other on-page
  text from <a href="/admin/translations">Translations</a>, and upload images/videos to use below
  from the <a href="/admin/media">Media Library</a>.
</p>

<form method="post" action="/admin/settings/header" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;">Website content — hero images &amp; video</h3>
  <p class="help-text">
    Add a background image or video to any marketing page's top banner. Upload it to the
    <a href="/admin/media" target="_blank" rel="noopener">Media Library</a> first, then paste its URL here.
    If a video URL is set it takes priority over the image. Leave both blank to keep the default design.
  </p>
  <?php foreach ($heroPages as $key => $label): ?>
    <div class="form-row" style="margin-bottom:4px;">
      <div class="form-group">
        <label><?= e($label) ?> — image URL</label>
        <input type="text" name="hero_image_<?= $key ?>" value="<?= e($settings["hero_image_{$key}"] ?? '') ?>" placeholder="/uploads/media/....jpg">
      </div>
      <div class="form-group">
        <label><?= e($label) ?> — video URL</label>
        <input type="text" name="hero_video_<?= $key ?>" value="<?= e($settings["hero_video_{$key}"] ?? '') ?>" placeholder="https://...">
      </div>
    </div>
  <?php endforeach; ?>

  <h3 style="font-size:14px;margin-top:22px;"><?= t('admin.settings.header') ?></h3>
  <div class="form-group">
    <label><?= t('admin.settings.header_phone') ?></label>
    <input type="text" name="header_phone" value="<?= e($settings['header_phone'] ?? '') ?>" placeholder="+966 11 000 0000">
  </div>

  <h3 style="font-size:14px;margin-top:22px;"><?= t('admin.settings.footer_content') ?></h3>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.tagline_en') ?></label><input type="text" name="footer_tagline_en" value="<?= e($settings['footer_tagline_en'] ?? '') ?>" placeholder="Construction management & job costing software for the Saudi market."></div>
    <div class="form-group"><label><?= t('admin.settings.tagline_ar') ?></label><input type="text" name="footer_tagline_ar" value="<?= e($settings['footer_tagline_ar'] ?? '') ?>" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.cities_en') ?></label><input type="text" name="footer_cities_en" value="<?= e($settings['footer_cities_en'] ?? '') ?>" placeholder="Riyadh · Jeddah · Dammam"></div>
    <div class="form-group"><label><?= t('admin.settings.cities_ar') ?></label><input type="text" name="footer_cities_ar" value="<?= e($settings['footer_cities_ar'] ?? '') ?>" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.extra_note_en') ?></label><input type="text" name="footer_bottom_note_en" value="<?= e($settings['footer_bottom_note_en'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.extra_note_ar') ?></label><input type="text" name="footer_bottom_note_ar" value="<?= e($settings['footer_bottom_note_ar'] ?? '') ?>" dir="rtl"></div>
  </div>

  <h3 style="font-size:14px;margin-top:22px;"><?= t('admin.settings.social_links') ?></h3>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.facebook_url') ?></label><input type="text" name="social_facebook_url" value="<?= e($settings['social_facebook_url'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.twitter_url') ?></label><input type="text" name="social_twitter_url" value="<?= e($settings['social_twitter_url'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.settings.instagram_url') ?></label><input type="text" name="social_instagram_url" value="<?= e($settings['social_instagram_url'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.settings.linkedin_url') ?></label><input type="text" name="social_linkedin_url" value="<?= e($settings['social_linkedin_url'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label><?= t('admin.settings.whatsapp_link') ?></label><input type="text" name="social_whatsapp_url" value="<?= e($settings['social_whatsapp_url'] ?? '') ?>"></div>

  <button type="submit" class="btn btn-primary" style="margin-top:10px;"><?= t('common.save_changes') ?></button>
</form>

@endsection
