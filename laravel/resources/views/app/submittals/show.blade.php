@extends('layouts.app')

@section('content')
<?php
  $submittalStatusBadge = ['submitted' => 'gray', 'under_review' => 'yellow', 'approved' => 'green', 'approved_as_noted' => 'green', 'rejected' => 'red', 'revise_resubmit' => 'red'][$submittal->status] ?? 'gray';
?>
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $submittal->project_id ?>">&larr; <?= e($submittal->project ? local($submittal->project, 'name') : '') ?></a></p>
    <h1><?= e($submittal->displayNumber()) ?> &middot; <?= e($submittal->title) ?></h1>
  </div>
  <a href="/app/projects/<?= $submittal->project_id ?>/submittals" class="btn btn-light btn-sm">&larr; <?= t('common.back') ?></a>
</div>

<div class="grid grid-2" style="align-items:start;grid-template-columns:2fr 1fr;">
  <div class="card">
    <h3><?= t('user.submittals.revision_history') ?></h3>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('user.submittals.revision_col') ?></th><th><?= t('common.file') ?></th><th><?= t('common.notes') ?></th><th><?= t('user.submittals.uploaded_by_col') ?></th><th><?= t('common.date') ?></th></tr></thead>
      <tbody>
      <?php foreach ($submittal->revisions as $rev): ?>
        <tr>
          <td><span class="badge badge-blue">Rev <?= (int) $rev->revision_number ?></span></td>
          <td><a href="<?= e($rev->file_path) ?>" target="_blank" download><?= e($rev->file_name) ?></a></td>
          <td class="help-text"><?= e($rev->notes ?: '—') ?></td>
          <td><?= e($rev->uploader->name ?? '—') ?></td>
          <td class="help-text"><?= e($rev->created_at->format('Y-m-d H:i')) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post" action="/app/submittals/<?= $submittal->id ?>/revisions" enctype="multipart/form-data" style="border-top:1px solid var(--border);padding-top:16px;">
      <?= csrf_field() ?>
      <h4 style="margin-bottom:8px;"><?= t('user.submittals.upload_new_revision') ?></h4>
      <div class="form-group"><label><?= t('user.submittals.file') ?></label><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.dwg,.zip"></div>
      <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
      <button type="submit" class="btn btn-primary"><?= t('common.upload') ?></button>
    </form>
  </div>

  <div class="card">
    <h3><?= t('user.submittals.details') ?></h3>
    <p class="help-text"><?= t('common.status') ?>: <span class="badge badge-<?= $submittalStatusBadge ?>"><?= e($statuses[$submittal->status] ?? ucfirst($submittal->status)) ?></span></p>
    <?php if ($submittal->spec_section): ?><p class="help-text"><?= t('user.submittals.spec_section') ?>: <?= e($submittal->spec_section) ?></p><?php endif; ?>
    <?php if ($submittal->description): ?><p class="help-text"><?= e($submittal->description) ?></p><?php endif; ?>
    <p class="help-text"><?= t('user.submittals.submitted_by') ?>: <?= e($submittal->submitter->name ?? '—') ?></p>
    <p class="help-text"><?= t('user.submittals.due_date') ?>: <?= e($submittal->due_date ? $submittal->due_date->format('Y-m-d') : '—') ?></p>
    <?php if ($submittal->reviewed_by): ?>
      <p class="help-text"><?= t('user.submittals.reviewed_by') ?>: <?= e($submittal->reviewer->name ?? '—') ?> (<?= e($submittal->reviewed_at?->format('Y-m-d H:i')) ?>)</p>
    <?php endif; ?>

    <?php if (auth()->user()->can('approve_documents')): ?>
      <form method="post" action="/app/submittals/<?= $submittal->id ?>/status" style="margin-top:12px;">
        <?= csrf_field() ?>
        <div class="form-group">
          <label><?= t('user.submittals.review_decision') ?></label>
          <select name="status">
            <?php foreach ($statuses as $key => $label): ?>
              <option value="<?= $key ?>" <?= $submittal->status === $key ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-outline btn-sm"><?= t('common.save') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
@endsection
