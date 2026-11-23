@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'maintenance'])

<form method="post" action="/admin/settings/maintenance" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;">🛠️ <?= t('admin.settings.maintenance_mode') ?></h3>
    <label style="font-weight:400;font-size:14px;"><input type="checkbox" name="maintenance_mode" value="1" style="width:auto;display:inline-block;" <?= !empty($settings['maintenance_mode']) ? 'checked' : '' ?>> <?= t('admin.settings.enabled') ?></label>
  </div>
  <p class="help-text"><?= t('admin.settings.maintenance_mode_hint') ?></p>

  <div class="form-group">
    <label><?= t('admin.settings.maintenance_message_en') ?></label>
    <textarea name="maintenance_message_en" rows="3" placeholder="We are performing scheduled maintenance. Please check back shortly."><?= e($settings['maintenance_message_en'] ?? '') ?></textarea>
  </div>
  <div class="form-group">
    <label><?= t('admin.settings.maintenance_message_ar') ?></label>
    <textarea name="maintenance_message_ar" rows="3" dir="rtl" placeholder="نقوم حاليًا بأعمال صيانة مجدولة. يرجى المحاولة مرة أخرى بعد قليل."><?= e($settings['maintenance_message_ar'] ?? '') ?></textarea>
  </div>

  <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
</form>

<div class="card" style="max-width:680px;margin-top:20px;">
  <h3><?= t('admin.settings.what_this_controls') ?></h3>
  <p class="help-text"><?= t('admin.settings.maintenance_scope_hint') ?></p>
</div>

@endsection
