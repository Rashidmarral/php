@extends('layouts.app')

@section('content')
<?php $ro = auth()->user()->isCompanyOwner() ? '' : 'disabled'; ?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'business'])

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

<?php
// The chain is a pure refinement of the require_*_approval toggles above — never a
// separate on/off switch — so its builder only ever shows for a document type whose
// own toggle is already on (and only while the plan still includes approval_workflow).
$showEstimateChain = \App\Support\Feature::allows('approval_workflow') && !empty($company['require_estimate_approval']);
$showInvoiceChain = \App\Support\Feature::allows('approval_workflow') && !empty($company['require_invoice_approval']);

/** @var \Illuminate\Support\Collection $stepsForSlot */
$stepForSlot = fn ($steps, int $slot) => $steps->firstWhere('step_order', $slot + 1);
?>

<?php if ($showEstimateChain || $showInvoiceChain): ?>
<form method="post" action="/app/settings/business/approval-chain" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <h3 style="font-size:14px;"><?= t('user.settings.approval_chain_title') ?></h3>
  <p class="help-text"><?= t('user.settings.approval_chain_hint') ?></p>

  <?php foreach ([
    ['show' => $showEstimateChain, 'type' => 'estimate', 'heading' => t('user.settings.approval_chain_estimates_heading'), 'steps' => $estimateChainSteps],
    ['show' => $showInvoiceChain, 'type' => 'invoice', 'heading' => t('user.settings.approval_chain_invoices_heading'), 'steps' => $invoiceChainSteps],
  ] as $block): ?>
    <?php if (!$block['show']): continue; endif; ?>
    <h4 style="font-size:13px;margin-top:16px;"><?= e($block['heading']) ?></h4>
    <?php for ($slot = 0; $slot < \App\Models\ApprovalChainStep::MAX_STEPS; $slot++): ?>
      <?php $step = $stepForSlot($block['steps'], $slot); ?>
      <div class="form-row">
        <div class="form-group">
          <label><?= t('user.settings.approval_chain_step_label', ['n' => $slot + 1]) ?> — <?= t('user.settings.approval_chain_role_label') ?></label>
          <select name="<?= $block['type'] ?>_step_role[]" <?= $ro ?>>
            <option value=""><?= t('user.approvals.none_option') ?></option>
            <?php foreach (\App\Models\ApprovalChainStep::ROLES as $roleOption): ?>
              <option value="<?= $roleOption ?>" <?= ($step?->role_required === $roleOption) ? 'selected' : '' ?>><?= t('user.approvals.role_' . $roleOption) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label><?= t('user.settings.approval_chain_custom_label_en') ?></label>
          <input type="text" name="<?= $block['type'] ?>_step_label[]" value="<?= e($step->label ?? '') ?>" <?= $ro ?>>
        </div>
        <div class="form-group">
          <label><?= t('user.settings.approval_chain_custom_label_ar') ?></label>
          <input type="text" name="<?= $block['type'] ?>_step_label_ar[]" value="<?= e($step->label_ar ?? '') ?>" dir="rtl" <?= $ro ?>>
        </div>
      </div>
    <?php endfor; ?>
  <?php endforeach; ?>

  <?php if (auth()->user()->isCompanyOwner()): ?>
    <button type="submit" class="btn btn-primary"><?= t('user.settings.approval_chain_save') ?></button>
  <?php else: ?>
    <p class="help-text"><?= t('user.settings.owner_only_hint') ?></p>
  <?php endif; ?>
</form>
<?php endif; ?>

@endsection
