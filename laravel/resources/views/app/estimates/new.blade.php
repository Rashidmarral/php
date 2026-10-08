@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <a href="/app/estimates" class="help-text"><?= t('user.estimates.back') ?></a>
    <h1 style="margin-top:6px;"><?= t('user.estimates.create_title') ?></h1>
  </div>
</div>

<div class="grid grid-3" style="margin-bottom:36px;">
  <a href="/app/estimates/create" class="card feature-card" style="text-decoration:none;color:inherit;">
    <div class="icon">📄</div>
    <h3><?= t('user.estimates.blank_title') ?></h3>
    <p><?= t('user.estimates.blank_hint') ?></p>
  </a>
  <?php if ($defaultTemplate): ?>
    <a href="/app/estimates/templates/<?= $defaultTemplate['id'] ?>" class="card feature-card" style="text-decoration:none;color:inherit;border-color:var(--brand);">
      <div class="icon">⭐</div>
      <h3><?= t('user.estimates.default_template_title') ?></h3>
      <p><?= t('user.estimates.currently_set_to') ?> <?= e(local($defaultTemplate, 'name_en', 'name_ar')) ?></p>
    </a>
  <?php endif; ?>
  <a href="/app/estimates/ai" class="card feature-card" style="text-decoration:none;color:inherit;background:linear-gradient(135deg,var(--brand-light),#fff);">
    <div class="icon">✨</div>
    <h3><?= t('user.estimates.ai_title') ?></h3>
    <p><?= t('user.estimates.ai_hint') ?></p>
  </a>
</div>

<div class="section-head" style="text-align:start;margin-bottom:20px;">
  <h2 style="font-size:19px;"><?= t('user.estimates.start_from_template') ?></h2>
  <p style="color:var(--muted);font-size:14px;"><?= t('user.estimates.start_from_template_hint') ?></p>
</div>

<h3 style="font-size:14px;color:var(--muted);margin-bottom:14px;"><?= t('user.estimates.templates_available') ?> (<?= count($templates) ?>)</h3>

<div class="grid grid-3">
  <?php foreach ($templates as $t): ?>
    <a href="/app/estimates/templates/<?= $t['id'] ?>" class="card feature-card" style="text-decoration:none;color:inherit;">
      <div class="icon"><?= e($t['icon']) ?></div>
      <h3><?= e(local($t, 'name_en', 'name_ar')) ?></h3>
      <p><?= e(local($t, 'description_en', 'description_ar')) ?></p>
    </a>
  <?php endforeach; ?>
</div>

@endsection
