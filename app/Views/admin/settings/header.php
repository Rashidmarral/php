<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Platform Settings</h1>
</div>

<div class="tabs">
  <a href="/admin/settings">General</a>
  <a href="/admin/settings/payments">Payment Methods</a>
  <a href="/admin/settings/legal">Legal & Branding</a>
  <a href="/admin/settings/header" class="active">Header & Footer</a>
  <a href="/admin/settings/ai">AI Generator</a>
  <a href="/admin/settings/notifications">Notifications</a>
  <a href="/admin/settings/email">Email</a>
</div>

<p class="help-text" style="margin-top:-8px;margin-bottom:20px;max-width:680px;">
  Leave any field blank to keep the site's default wording. Manage which custom pages appear
  in the nav/footer from <a href="/admin/pages">Website Pages</a>, and edit any other on-page
  text from <a href="/admin/translations">Translations</a>.
</p>

<form method="post" action="/admin/settings/header" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>

  <h3 style="font-size:14px;">Header</h3>
  <div class="form-group">
    <label>Header phone number (optional)</label>
    <input type="text" name="header_phone" value="<?= View::e($settings['header_phone'] ?? '') ?>" placeholder="+966 11 000 0000">
  </div>

  <h3 style="font-size:14px;margin-top:22px;">Footer content</h3>
  <div class="form-row">
    <div class="form-group"><label>Tagline (English)</label><input type="text" name="footer_tagline_en" value="<?= View::e($settings['footer_tagline_en'] ?? '') ?>" placeholder="Construction management & job costing software for the Saudi market."></div>
    <div class="form-group"><label>Tagline (Arabic)</label><input type="text" name="footer_tagline_ar" value="<?= View::e($settings['footer_tagline_ar'] ?? '') ?>" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Cities line (English)</label><input type="text" name="footer_cities_en" value="<?= View::e($settings['footer_cities_en'] ?? '') ?>" placeholder="Riyadh · Jeddah · Dammam"></div>
    <div class="form-group"><label>Cities line (Arabic)</label><input type="text" name="footer_cities_ar" value="<?= View::e($settings['footer_cities_ar'] ?? '') ?>" dir="rtl"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Extra footer note (English)</label><input type="text" name="footer_bottom_note_en" value="<?= View::e($settings['footer_bottom_note_en'] ?? '') ?>"></div>
    <div class="form-group"><label>Extra footer note (Arabic)</label><input type="text" name="footer_bottom_note_ar" value="<?= View::e($settings['footer_bottom_note_ar'] ?? '') ?>" dir="rtl"></div>
  </div>

  <h3 style="font-size:14px;margin-top:22px;">Social links (shown in footer when filled in)</h3>
  <div class="form-row">
    <div class="form-group"><label>Facebook URL</label><input type="text" name="social_facebook_url" value="<?= View::e($settings['social_facebook_url'] ?? '') ?>"></div>
    <div class="form-group"><label>X / Twitter URL</label><input type="text" name="social_twitter_url" value="<?= View::e($settings['social_twitter_url'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Instagram URL</label><input type="text" name="social_instagram_url" value="<?= View::e($settings['social_instagram_url'] ?? '') ?>"></div>
    <div class="form-group"><label>LinkedIn URL</label><input type="text" name="social_linkedin_url" value="<?= View::e($settings['social_linkedin_url'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label>WhatsApp link (e.g. https://wa.me/9665xxxxxxxx)</label><input type="text" name="social_whatsapp_url" value="<?= View::e($settings['social_whatsapp_url'] ?? '') ?>"></div>

  <button type="submit" class="btn btn-primary" style="margin-top:10px;">Save changes</button>
</form>
