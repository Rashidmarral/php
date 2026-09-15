@extends('layouts.app')

@section('content')
<?php $ro = auth()->user()->isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/settings"><?= t('user.settings.tab_profile') ?></a>
  <a href="/app/settings/legal"><?= t('user.settings.tab_legal') ?></a>
  <a href="/app/settings/business" class="active"><?= t('user.settings.tab_business') ?></a>
  <a href="/app/settings/security"><?= t('user.settings.tab_security') ?></a>
</div>

<form method="post" action="/app/settings/business" class="card" style="max-width:680px;">
  <?= csrf_field() ?>

  <h3 style="font-size:14px;"><?= t('user.settings.business_controls') ?></h3>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.default_markup') ?></label>
      <input type="number" step="0.01" name="default_markup_percent" value="<?= e((string)($company['default_markup_percent'] ?? 0)) ?>" <?= $ro ?>>
      <p class="help-text"><?= t('user.settings.default_markup_hint') ?></p>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="client_portal_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($company['client_portal_enabled']) ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.enable_client_portal') ?></label>
      <p class="help-text"><?= t('user.settings.client_portal_hint') ?></p>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.settings.default_retention') ?></label>
      <input type="number" step="0.01" name="default_retention_percent" value="<?= e((string)($company['default_retention_percent'] ?? 0)) ?>" <?= $ro ?>>
      <p class="help-text"><?= t('user.settings.default_retention_hint') ?></p>
    </div>
  </div>

  <?php if (\App\Support\Feature::allows('approval_workflow')): ?>
    <div class="form-row">
      <div class="form-group">
        <label><input type="checkbox" name="require_estimate_approval" value="1" style="width:auto;display:inline-block;" <?= !empty($company['require_estimate_approval']) ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.require_estimate_approval') ?></label>
        <p class="help-text"><?= t('user.settings.require_estimate_approval_hint') ?></p>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="require_invoice_approval" value="1" style="width:auto;display:inline-block;" <?= !empty($company['require_invoice_approval']) ? 'checked' : '' ?> <?= $ro ?>> <?= t('user.settings.require_invoice_approval') ?></label>
        <p class="help-text"><?= t('user.settings.require_invoice_approval_hint') ?></p>
      </div>
    </div>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.approval_workflow_upsell') ?> <a href="/app/billing"><?= t('user.team.upgrade_plan') ?></a></p>
  <?php endif; ?>

  <?php if (auth()->user()->isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.owner_only_hint') ?></p>
  <?php endif; ?>
</form>

@endsection
