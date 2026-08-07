<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.pages.title') ?></h1>
  <a href="/admin/pages/create" class="btn btn-primary">+ <?= t('admin.pages.new') ?></a>
</div>
<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  Custom pages you write live at <code>/p/&lt;slug&gt;</code> and can optionally appear in the site header nav and/or footer.
</p>

<?php if (empty($pages)): ?>
  <div class="empty-state card">
    <div class="icon">📄</div>
    <p><?= t('admin.pages.no_pages') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('admin.pages.title_en_col') ?></th><th><?= t('admin.pages.url') ?></th><th><?= t('admin.pages.in_nav') ?></th><th><?= t('admin.pages.in_footer') ?></th><th><?= t('admin.pages.published') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><?= View::e($p['title_en']) ?><br><span class="help-text"><?= View::e($p['title_ar']) ?></span></td>
        <td><a href="/p/<?= View::e($p['slug']) ?>" target="_blank">/p/<?= View::e($p['slug']) ?></a></td>
        <td><?= $p['show_in_nav'] ? '<span class="badge badge-green">' . t('common.yes') . '</span>' : '<span class="badge badge-gray">' . t('common.no') . '</span>' ?></td>
        <td><?= $p['show_in_footer'] ? '<span class="badge badge-green">' . t('common.yes') . '</span>' : '<span class="badge badge-gray">' . t('common.no') . '</span>' ?></td>
        <td><?= $p['is_published'] ? '<span class="badge badge-green">' . t('admin.pages.published_badge') . '</span>' : '<span class="badge badge-yellow">' . t('admin.pages.draft') . '</span>' ?></td>
        <td style="display:flex;gap:6px;">
          <a href="/admin/pages/<?= $p['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <form method="post" action="/admin/pages/<?= $p['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.pages.delete_confirm') ?>');" style="display:inline;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
