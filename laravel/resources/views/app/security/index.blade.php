@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.security.title') ?></h1>
</div>

@if (session('2fa_recovery_codes'))
  <div class="card" style="max-width:560px;border:1px solid var(--warning, #d97706);">
    <h3 style="font-size:14px;margin-top:0;">🔑 <?= t('user.security.recovery_codes_title') ?></h3>
    <p class="help-text"><?= t('user.security.recovery_codes_warning') ?></p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-family:monospace;font-size:14px;background:var(--bg-soft,#f6f7f8);border-radius:8px;padding:14px;margin:12px 0;">
      @foreach (session('2fa_recovery_codes') as $rc)
        <div>{{ $rc }}</div>
      @endforeach
    </div>
    <p class="help-text"><?= t('user.security.recovery_codes_saved_hint') ?></p>
  </div>
@endif

@if (!$enabled)
  <div class="card" style="max-width:560px;">
    <h3 style="font-size:14px;margin-top:0;"><?= t('user.security.enable_title') ?></h3>
    <p class="help-text"><?= t('user.security.enable_hint') ?></p>

    <div style="text-align:center;margin:16px 0;">
      <img src="<?= $qr ?>" width="200" height="200" alt="QR code" style="border:1px solid var(--border);border-radius:8px;">
    </div>

    <div class="form-group">
      <label><?= t('user.security.manual_entry') ?></label>
      <input type="text" readonly value="<?= e($secret) ?>" style="font-family:monospace;letter-spacing:1px;" onclick="this.select()">
    </div>

    <form method="post" action="/app/security/enable" style="margin-top:16px;">
      @csrf
      <div class="form-group">
        <label><?= t('user.security.confirm_code_label') ?></label>
        <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" autocomplete="one-time-code" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary"><?= t('user.security.confirm_enable') ?></button>
    </form>
  </div>
@else
  <div class="card" style="max-width:560px;">
    <h3 style="font-size:14px;margin-top:0;"><?= t('user.security.status_title') ?> <span class="badge badge-green"><?= t('user.security.status_on') ?></span></h3>
    <p class="help-text"><?= t('user.security.status_hint') ?></p>
    <p class="help-text"><?= t('user.security.recovery_codes_remaining', ['count' => $recoveryCodesRemaining]) ?></p>

    <details style="margin:16px 0;">
      <summary style="cursor:pointer;font-weight:600;font-size:13px;"><?= t('user.security.regenerate_title') ?></summary>
      <form method="post" action="/app/security/recovery-codes" style="margin-top:12px;">
        @csrf
        <p class="help-text"><?= t('user.security.regenerate_hint') ?></p>
        <div class="form-row">
          <div class="form-group">
            <label><?= t('common.current_password') ?></label>
            <div class="password-field">
              <input type="password" name="password" autocomplete="current-password">
              {!! passwordToggle() !!}
            </div>
          </div>
          <div class="form-group">
            <label><?= t('user.security.or_auth_code') ?></label>
            <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" autocomplete="one-time-code">
          </div>
        </div>
        <button type="submit" class="btn btn-outline"><?= t('user.security.regenerate_button') ?></button>
      </form>
    </details>

    <details style="margin:16px 0;">
      <summary style="cursor:pointer;font-weight:600;font-size:13px;color:var(--danger);"><?= t('user.security.disable_title') ?></summary>
      <form method="post" action="/app/security/disable" style="margin-top:12px;">
        @csrf
        <p class="help-text"><?= t('user.security.disable_hint') ?></p>
        <div class="form-row">
          <div class="form-group">
            <label><?= t('common.current_password') ?></label>
            <div class="password-field">
              <input type="password" name="password" autocomplete="current-password">
              {!! passwordToggle() !!}
            </div>
          </div>
          <div class="form-group">
            <label><?= t('user.security.or_auth_code') ?></label>
            <input type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" autocomplete="one-time-code">
          </div>
        </div>
        <button type="submit" class="btn btn-danger"><?= t('user.security.disable_button') ?></button>
      </form>
    </details>
  </div>
@endif

@endsection
