@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'header'])

<p class="help-text" style="margin-top:-8px;margin-bottom:20px;max-width:680px;">
  <?= t('admin.settings.header_intro_before') ?> <a href="/admin/pages"><?= t('admin.pages.title') ?></a>, <?= t('admin.settings.header_intro_mid') ?> <a href="/admin/translations"><?= t('admin.settings.translations_title') ?></a>, <?= t('admin.settings.header_intro_after') ?>
  <a href="/admin/media"><?= t('admin.media.title') ?></a>.
</p>

<form method="post" action="/admin/settings/header" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;"><?= t('admin.settings.hero_content_title') ?></h3>
  <p class="help-text">
    <?= t('admin.settings.hero_content_before') ?>
    <a href="/admin/media" target="_blank" rel="noopener"><?= t('admin.media.title') ?></a> <?= t('admin.settings.hero_content_after') ?>
  </p>
  <?php foreach ($heroPages as $key => $label): ?>
    <div class="form-row" style="margin-bottom:4px;">
      <div class="form-group">
        <label><?= e($label) ?> <?= t('admin.settings.image_url_suffix') ?></label>
        <input type="text" name="hero_image_<?= $key ?>" value="<?= e($settings["hero_image_{$key}"] ?? '') ?>" placeholder="/uploads/media/....jpg">
      </div>
      <div class="form-group">
        <label><?= e($label) ?> <?= t('admin.settings.video_url_suffix') ?></label>
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
