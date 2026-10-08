<?php use App\Core\View; use App\Core\Csrf; $isEdit = $page !== null; ?>
<div class="page-head">
  <h1><?= $isEdit ? t('admin.pages.edit') : t('admin.pages.new_title') ?></h1>
  <a href="/admin/pages" class="btn btn-light">← <?= t('admin.pages.back') ?></a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/pages/' . $page['id'] : '/admin/pages' ?>">
  <?= Csrf::field() ?>

  <div class="card" style="margin-bottom:20px;">
    <h3><?= t('admin.pages.url_visibility') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.pages.slug') ?></label>
        <input type="text" name="slug" value="<?= View::e($page['slug'] ?? '') ?>" placeholder="e.g. careers" required>
        <p class="help-text">Page will be published at /p/&lt;slug&gt;</p>
      </div>
      <div class="form-group">
        <label><?= t('admin.pages.nav_order') ?></label>
        <input type="number" name="nav_order" value="<?= View::e((string)($page['nav_order'] ?? 0)) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_in_nav" value="1" <?= !empty($page['show_in_nav']) ? 'checked' : '' ?>> <?= t('admin.pages.show_in_nav') ?></label></div>
      <div class="form-group"><label><input type="checkbox" name="show_in_footer" value="1" <?= !empty($page['show_in_footer']) ? 'checked' : '' ?>> <?= t('admin.pages.show_in_footer') ?></label></div>
      <div class="form-group"><label><input type="checkbox" name="is_published" value="1" <?= ($page === null || !empty($page['is_published'])) ? 'checked' : '' ?>> <?= t('admin.pages.is_published') ?></label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.pages.nav_label_en') ?></label><input type="text" name="nav_label_en" value="<?= View::e($page['nav_label_en'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('admin.pages.nav_label_ar') ?></label><input type="text" name="nav_label_ar" value="<?= View::e($page['nav_label_ar'] ?? '') ?>" dir="rtl"></div>
    </div>
  </div>

  <div class="grid grid-2" style="gap:20px;margin-bottom:20px;">
    <div class="card">
      <h3><?= t('admin.pages.english_content') ?></h3>
      <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title_en" value="<?= View::e($page['title_en'] ?? '') ?>" required></div>
      <div class="form-group"><label><?= t('admin.pages.meta_description') ?></label><input type="text" name="meta_description_en" value="<?= View::e($page['meta_description_en'] ?? '') ?>"></div>
      <div class="form-group">
        <label><?= t('admin.pages.body_html') ?></label>
        <textarea name="content_en" rows="16" style="font-family:monospace;font-size:13px;"><?= View::e($page['content_en'] ?? '') ?></textarea>
        <p class="help-text">Basic HTML tags supported: &lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;a&gt;, &lt;strong&gt;, &lt;img&gt;.</p>
      </div>
    </div>
    <div class="card">
      <h3><?= t('admin.pages.arabic_content') ?></h3>
      <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title_ar" value="<?= View::e($page['title_ar'] ?? '') ?>" dir="rtl" required></div>
      <div class="form-group"><label><?= t('admin.pages.meta_description') ?></label><input type="text" name="meta_description_ar" value="<?= View::e($page['meta_description_ar'] ?? '') ?>" dir="rtl"></div>
      <div class="form-group">
        <label><?= t('admin.pages.body_html') ?></label>
        <textarea name="content_ar" rows="16" dir="rtl" style="font-family:monospace;font-size:13px;"><?= View::e($page['content_ar'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><?= $isEdit ? t('common.save_changes') : t('admin.pages.create') ?></button>
  <?php if ($isEdit): ?>
    <a href="/p/<?= View::e($page['slug']) ?>" target="_blank" class="btn btn-light"><?= t('admin.pages.preview') ?></a>
  <?php endif; ?>
</form>
