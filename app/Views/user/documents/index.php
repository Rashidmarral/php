<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Documents</h1>
</div>

<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;">Upload a document</h3>
  <form method="post" action="/app/documents" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;">
      <label>File</label>
      <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip">
    </div>
    <div class="form-group" style="margin:0;">
      <label>Project (optional)</label>
      <select name="project_id">
        <option value="">— None —</option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label>Arabic label (optional)</label>
      <input type="text" name="name_ar" dir="rtl" placeholder="اسم الملف بالعربية">
    </div>
    <button type="submit" class="btn btn-primary">Upload</button>
  </form>
  <p class="help-text" style="margin-top:8px;">PDF, images, Office documents, or ZIP — up to 15MB.</p>
</div>

<?php if (empty($documents)): ?>
  <div class="card empty-state">
    <div class="icon">📁</div>
    <h3>No documents yet</h3>
    <p>Upload contracts, drawings, permits, or photos and optionally link them to a project.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Project</th><th>Type</th><th>Size</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $d): ?>
      <tr>
        <td><a href="<?= View::e($d['file_path']) ?>" target="_blank" download><?= View::e(View::local($d, 'name')) ?></a></td>
        <td><?= View::e($d['project_name'] ? View::local($d, 'project_name') : '—') ?></td>
        <td><span class="badge badge-gray"><?= View::e(strtoupper($d['file_type'])) ?></span></td>
        <td class="help-text"><?= number_format($d['file_size'] / 1024, 0) ?> KB</td>
        <td><?= View::e($d['uploaded_by_name'] ?? '—') ?></td>
        <td class="help-text"><?= View::e($d['created_at']) ?></td>
        <td>
          <form method="post" action="/app/documents/<?= $d['id'] ?>/delete" onsubmit="return confirm('Delete this document?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-light">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
