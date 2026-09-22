@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'storage'])

<div class="card" style="max-width:680px;margin-bottom:20px;">
  <h3 style="font-size:14px;"><?= t('admin.settings.storage_overview') ?></h3>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.default_disk') ?></label>
      <input type="text" value="<?= e($disk) ?>" disabled>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('admin.settings.storage_app_usage') ?></label>
      <input type="text" value="<?= e(formatBytes($appUsageBytes)) ?>" disabled>
    </div>
    <div class="form-group">
      <label><?= t('admin.settings.storage_uploads_usage') ?></label>
      <input type="text" value="<?= e(formatBytes($uploadsUsageBytes)) ?>" disabled>
    </div>
  </div>
  <p class="help-text"><?= t('admin.settings.storage_usage_hint') ?></p>
</div>

<form method="post" action="/admin/settings/storage" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div class="form-group">
    <label><?= t('admin.settings.max_upload_size_mb') ?></label>
    <input type="number" name="max_upload_size_mb" min="0" value="<?= e($settings['max_upload_size_mb'] ?? '0') ?>" placeholder="0">
    <p class="help-text"><?= t('admin.settings.max_upload_size_mb_hint') ?></p>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
</form>

@endsection
