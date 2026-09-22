@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'security'])

<form method="post" action="/app/settings/security" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <h3 style="font-size:14px;"><?= t('user.settings.change_password') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.settings.change_password_hint') ?></p>
  <div class="form-group">
    <label><?= t('common.current_password') ?></label>
    <div class="password-field">
      <input type="password" name="current_password" required autocomplete="current-password">
      <?= passwordToggle() ?>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.new_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <?= passwordToggle() ?>
      </div>
    </div>
    <div class="form-group">
      <label><?= t('user.settings.confirm_new_password') ?></label>
      <div class="password-field">
        <input type="password" name="new_password_confirm" required minlength="8" autocomplete="new-password">
        <?= passwordToggle() ?>
      </div>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('user.settings.update_password') ?></button>
</form>

@endsection
