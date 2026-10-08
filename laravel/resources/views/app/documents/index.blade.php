@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.documents.title') ?></h1>
  <a href="/app/documents<?= $showHistory ? '' : '?show_history=1' ?>" class="btn btn-light btn-sm">
    <?= $showHistory ? t('user.documents.hide_history') : t('user.documents.show_history') ?>
  </a>
</div>

<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;"><?= t('user.documents.upload_a_document') ?></h3>
  <form method="post" action="/app/documents" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.file') ?></label>
      <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip">
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.documents.project_optional') ?></label>
      <select name="project_id">
        <option value=""><?= t('user.invoices.no_project') ?></option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.documents.arabic_label_optional') ?></label>
      <input type="text" name="name_ar" dir="rtl" placeholder="اسم الملف بالعربية">
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.upload') ?></button>
  </form>
  <p class="help-text" style="margin-top:8px;"><?= t('user.documents.upload_hint') ?></p>
</div>

<?php if (empty($documents)): ?>
  <div class="card empty-state">
    <div class="icon">📁</div>
    <h3><?= t('user.documents.no_documents_title') ?></h3>
    <p><?= t('user.documents.no_documents_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.project') ?></th><th><?= t('common.type') ?></th><th><?= t('user.documents.version_col') ?></th><th><?= t('user.documents.size_col') ?></th><th><?= t('user.documents.uploaded_by_col') ?></th><th><?= t('common.date') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($documents as $d): ?>
      <tr>
        <td>
          <a href="<?= e($d['file_path']) ?>" target="_blank" download><?= e(local($d, 'name')) ?></a>
          <?php if (!empty($versionChains[$d['id']])): ?>
            <div class="help-text" style="margin-top:2px;">
              <?= t('user.documents.versions_label') ?>:
              <?php foreach ($versionChains[$d['id']] as $i => $v): ?>
                <?php if ($i > 0): ?> &rarr; <?php endif; ?>
                <a href="<?= e($v['file_path']) ?>" target="_blank"><?= 'v' . (int)$v['version'] ?><?= $v['is_current'] ? ' (' . t('user.documents.current_version') . ')' : '' ?></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </td>
        <td><?= e($d['project_name'] ? local($d, 'project_name') : '—') ?></td>
        <td><span class="badge badge-gray"><?= e(strtoupper($d['file_type'])) ?></span></td>
        <td>
          <span class="badge badge-<?= $d['is_current'] ? 'blue' : 'gray' ?>">
            v<?= (int)$d['version'] ?><?= $d['is_current'] ? '' : ' (' . t('user.documents.superseded') . ')' ?>
          </span>
        </td>
        <td class="help-text"><?= number_format($d['file_size'] / 1024, 0) ?> KB</td>
        <td><?= e($d['uploaded_by_name'] ?? '—') ?></td>
        <td class="help-text"><?= e($d['created_at']) ?></td>
        <td style="white-space:nowrap;">
          <?php if ($d['is_current']): ?>
            <details style="display:inline-block;">
              <summary class="btn btn-sm btn-outline" style="cursor:pointer;display:inline-block;"><?= t('user.documents.upload_new_version') ?></summary>
              <div class="card" style="margin-top:8px;min-width:280px;">
                <form method="post" action="/app/documents/<?= $d['id'] ?>/version" enctype="multipart/form-data">
                  <?= csrf_field() ?>
                  <div class="form-group" style="margin:0 0 8px;">
                    <label><?= t('common.file') ?></label>
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip">
                  </div>
                  <button type="submit" class="btn btn-sm btn-primary"><?= t('common.upload') ?></button>
                </form>
              </div>
            </details>
          <?php endif; ?>
          <form method="post" action="/app/documents/<?= $d['id'] ?>/delete" onsubmit="return confirm('<?= t('user.documents.delete_confirm') ?>');" style="display:inline-block;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
