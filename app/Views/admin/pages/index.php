<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Website Pages</h1>
  <a href="/admin/pages/create" class="btn btn-primary">+ New Page</a>
</div>
<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  Custom pages you write live at <code>/p/&lt;slug&gt;</code> and can optionally appear in the site header nav and/or footer.
</p>

<?php if (empty($pages)): ?>
  <div class="empty-state card">
    <div class="icon">📄</div>
    <p>No custom pages yet. Create one to add extra content to the public website.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Title (EN)</th><th>URL</th><th>In nav?</th><th>In footer?</th><th>Published?</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><?= View::e($p['title_en']) ?><br><span class="help-text"><?= View::e($p['title_ar']) ?></span></td>
        <td><a href="/p/<?= View::e($p['slug']) ?>" target="_blank">/p/<?= View::e($p['slug']) ?></a></td>
        <td><?= $p['show_in_nav'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-gray">No</span>' ?></td>
        <td><?= $p['show_in_footer'] ? '<span class="badge badge-green">Yes</span>' : '<span class="badge badge-gray">No</span>' ?></td>
        <td><?= $p['is_published'] ? '<span class="badge badge-green">Published</span>' : '<span class="badge badge-yellow">Draft</span>' ?></td>
        <td style="display:flex;gap:6px;">
          <a href="/admin/pages/<?= $p['id'] ?>/edit" class="btn btn-sm btn-light">Edit</a>
          <form method="post" action="/admin/pages/<?= $p['id'] ?>/delete" onsubmit="return confirm('Delete this page?');" style="display:inline;">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
