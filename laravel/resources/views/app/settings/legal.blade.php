@extends('layouts.app')

@section('content')
<?php $ro = auth()->user()->isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'legal'])

<form method="post" action="/app/settings/legal" enctype="multipart/form-data" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;"><?= t('user.settings.legal_documents') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.legal_docs_hint') ?></p>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.cr_certificate') ?></label>
      <?php if (!empty($company['cr_document_path'])): ?>
        <p class="help-text"><a href="<?= e($company['cr_document_path']) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
      <?php endif; ?>
      <?php if (auth()->user()->isCompanyOwner()): ?><input type="file" name="cr_document" accept="application/pdf,image/png,image/jpeg"><?php endif; ?>
    </div>
    <div class="form-group">
      <label><?= t('user.settings.vat_certificate') ?></label>
      <?php if (!empty($company['vat_document_path'])): ?>
        <p class="help-text"><a href="<?= e($company['vat_document_path']) ?>" target="_blank" rel="noopener"><?= t('user.settings.view_uploaded_file') ?></a></p>
      <?php endif; ?>
      <?php if (auth()->user()->isCompanyOwner()): ?><input type="file" name="vat_document" accept="application/pdf,image/png,image/jpeg"><?php endif; ?>
    </div>
  </div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.settings.classification') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.classification_hint') ?></p>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.classification_grade') ?></label>
      <select name="contractor_classification" <?= $ro ?>>
        <option value=""><?= t('user.settings.not_classified') ?></option>
        <?php foreach (['1', '2', '3', '4', '5'] as $val): ?>
          <option value="<?= $val ?>" <?= ($company['contractor_classification'] ?? '') === $val ? 'selected' : '' ?>><?= t('user.settings.classification_grade_option', ['n' => $val]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.settings.classification_number') ?></label><input type="text" name="contractor_classification_number" value="<?= e($company['contractor_classification_number'] ?? '') ?>" <?= $ro ?>></div>
  </div>

  <?php if (auth()->user()->isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.owner_only_hint') ?></p>
  <?php endif; ?>
</form>

@endsection
