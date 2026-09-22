@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'signup'])

<form method="post" action="/admin/settings/signup" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <div class="form-group">
    <label><?= t('admin.settings.trial_days') ?></label>
    <input type="number" name="trial_days" min="0" value="<?= e($settings['trial_days'] ?? '14') ?>">
    <p class="help-text"><?= t('admin.settings.trial_days_hint') ?></p>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('admin.settings.save_settings') ?></button>
</form>

<div class="card" style="max-width:680px;margin-top:20px;">
  <h3><?= t('admin.settings.signup_gaps_title') ?></h3>
  <p class="help-text"><?= t('admin.settings.signup_gaps_body') ?></p>
</div>

@endsection
