<?php use App\Core\View; use App\Core\Csrf; $isEdit = $page !== null; ?>
<div class="page-head">
  <h1><?= $isEdit ? 'Edit Page' : 'New Page' ?></h1>
  <a href="/admin/pages" class="btn btn-light">← Back to Pages</a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/pages/' . $page['id'] : '/admin/pages' ?>">
  <?= Csrf::field() ?>

  <div class="card" style="margin-bottom:20px;">
    <h3>URL &amp; visibility</h3>
    <div class="form-row">
      <div class="form-group">
        <label>URL slug</label>
        <input type="text" name="slug" value="<?= View::e($page['slug'] ?? '') ?>" placeholder="e.g. careers" required>
        <p class="help-text">Page will be published at /p/&lt;slug&gt;</p>
      </div>
      <div class="form-group">
        <label>Nav order (lower = earlier)</label>
        <input type="number" name="nav_order" value="<?= View::e((string)($page['nav_order'] ?? 0)) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_in_nav" value="1" <?= !empty($page['show_in_nav']) ? 'checked' : '' ?>> Show in header navigation</label></div>
      <div class="form-group"><label><input type="checkbox" name="show_in_footer" value="1" <?= !empty($page['show_in_footer']) ? 'checked' : '' ?>> Show in footer "Company" column</label></div>
      <div class="form-group"><label><input type="checkbox" name="is_published" value="1" <?= ($page === null || !empty($page['is_published'])) ? 'checked' : '' ?>> Published (visible to visitors)</label></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Nav label (English) — optional, defaults to title</label><input type="text" name="nav_label_en" value="<?= View::e($page['nav_label_en'] ?? '') ?>"></div>
      <div class="form-group"><label>Nav label (Arabic)</label><input type="text" name="nav_label_ar" value="<?= View::e($page['nav_label_ar'] ?? '') ?>" dir="rtl"></div>
    </div>
  </div>

  <div class="grid grid-2" style="gap:20px;margin-bottom:20px;">
    <div class="card">
      <h3>English content</h3>
      <div class="form-group"><label>Title</label><input type="text" name="title_en" value="<?= View::e($page['title_en'] ?? '') ?>" required></div>
      <div class="form-group"><label>Meta description</label><input type="text" name="meta_description_en" value="<?= View::e($page['meta_description_en'] ?? '') ?>"></div>
      <div class="form-group">
        <label>Body (HTML)</label>
        <textarea name="content_en" rows="16" style="font-family:monospace;font-size:13px;"><?= View::e($page['content_en'] ?? '') ?></textarea>
        <p class="help-text">Basic HTML tags supported: &lt;p&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;a&gt;, &lt;strong&gt;, &lt;img&gt;.</p>
      </div>
    </div>
    <div class="card">
      <h3>Arabic content</h3>
      <div class="form-group"><label>Title</label><input type="text" name="title_ar" value="<?= View::e($page['title_ar'] ?? '') ?>" dir="rtl" required></div>
      <div class="form-group"><label>Meta description</label><input type="text" name="meta_description_ar" value="<?= View::e($page['meta_description_ar'] ?? '') ?>" dir="rtl"></div>
      <div class="form-group">
        <label>Body (HTML)</label>
        <textarea name="content_ar" rows="16" dir="rtl" style="font-family:monospace;font-size:13px;"><?= View::e($page['content_ar'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create page' ?></button>
  <?php if ($isEdit): ?>
    <a href="/p/<?= View::e($page['slug']) ?>" target="_blank" class="btn btn-light">Preview →</a>
  <?php endif; ?>
</form>
