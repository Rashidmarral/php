@extends('layouts.app')

@section('content')
<?php
  // Display name + one-line description for each template, keyed the same as
  // $previews (Company::INVOICE_TEMPLATES order) so the loop below can pull
  // both the live-rendered preview and its label/copy together.
  $templateMeta = [
    'modern' => ['name' => t('common.pdf_template_modern'), 'desc' => t('user.settings.invoice_template_desc_modern')],
    'classic' => ['name' => t('common.pdf_template_classic'), 'desc' => t('user.settings.invoice_template_desc_classic')],
    'minimal' => ['name' => t('common.pdf_template_minimal'), 'desc' => t('user.settings.invoice_template_desc_minimal')],
    'bold' => ['name' => t('common.pdf_template_bold'), 'desc' => t('user.settings.invoice_template_desc_bold')],
    'elegant' => ['name' => t('common.pdf_template_elegant'), 'desc' => t('user.settings.invoice_template_desc_elegant')],
    'saudi' => ['name' => t('common.pdf_template_saudi'), 'desc' => t('user.settings.invoice_template_desc_saudi'), 'badge' => t('user.settings.invoice_template_bilingual_badge')],
  ];
  $canManage = auth()->user()->can('manage_company_settings');
?>
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'invoice_templates'])

<div class="section-head" style="text-align:start;margin-bottom:20px;">
  <h2 style="font-size:19px;"><?= t('user.settings.invoice_templates_heading') ?></h2>
  <p style="color:var(--muted);font-size:14px;max-width:720px;"><?= t('user.settings.invoice_templates_hint') ?></p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:20px;">
  <?php foreach ($previews as $key => $html): ?>
    <?php $meta = $templateMeta[$key]; $isActive = $activeTemplate === $key; ?>
    <div class="card" style="width:270px;padding:14px;<?= $isActive ? 'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);' : '' ?><?= $key === 'saudi' ? 'border-top:3px solid var(--brand);' : '' ?>">
      <div style="width:238px;height:210px;overflow:hidden;border:1px solid var(--border);border-radius:8px;position:relative;background:#fff;margin:0 auto;">
        <iframe srcdoc="<?= e($html) ?>" style="width:794px;height:1123px;border:0;position:absolute;top:0;left:0;transform:scale(0.3);transform-origin:top left;" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
      </div>

      <div style="margin-top:12px;">
        <div style="display:flex;justify-content:space-between;align-items:start;gap:6px;">
          <h3 style="font-size:15px;margin:0;"><?= e($meta['name']) ?></h3>
          <?php if ($isActive): ?>
            <span class="badge badge-green" style="white-space:nowrap;"><?= t('user.settings.currently_active') ?></span>
          <?php endif; ?>
        </div>
        <?php if (!empty($meta['badge'])): ?>
          <span class="badge badge-green" style="margin-top:6px;display:inline-block;"><?= e($meta['badge']) ?></span>
        <?php endif; ?>
        <p class="help-text" style="margin-top:6px;"><?= e($meta['desc']) ?></p>
      </div>

      <?php if ($isActive): ?>
        <button type="button" class="btn btn-outline" style="width:100%;margin-top:10px;" disabled><?= t('user.settings.activate_invoice_layout') ?></button>
      <?php elseif ($canManage): ?>
        <form method="post" action="/app/settings/invoice-templates" style="margin-top:10px;">
          <?= csrf_field() ?>
          <input type="hidden" name="template" value="<?= e($key) ?>">
          <button type="submit" class="btn btn-outline" style="width:100%;"><?= t('user.settings.activate_invoice_layout') ?></button>
        </form>
      <?php else: ?>
        <p class="help-text" style="margin-top:10px;"><?= t('user.settings.manage_settings_required_hint') ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

@endsection
