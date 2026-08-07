<?php use App\Core\View; ?>
<div class="page-head">
  <div>
    <a href="/app/estimates" class="help-text">← Back</a>
    <h1 style="margin-top:6px;">Create an Estimate</h1>
  </div>
</div>

<div class="grid grid-3" style="margin-bottom:36px;">
  <a href="/app/estimates/create" class="card feature-card" style="text-decoration:none;color:inherit;">
    <div class="icon">📄</div>
    <h3>Create a blank estimate</h3>
    <p>Start from scratch and add your own line items.</p>
  </a>
  <?php if ($defaultTemplate): ?>
    <a href="/app/estimates/templates/<?= $defaultTemplate['id'] ?>" class="card feature-card" style="text-decoration:none;color:inherit;border-color:var(--brand);">
      <div class="icon">⭐</div>
      <h3>Use default template</h3>
      <p>Currently set to <?= View::e(View::local($defaultTemplate, 'name_en', 'name_ar')) ?></p>
    </a>
  <?php endif; ?>
  <a href="/app/estimates/ai" class="card feature-card" style="text-decoration:none;color:inherit;background:linear-gradient(135deg,var(--brand-light),#fff);">
    <div class="icon">✨</div>
    <h3>AI Estimate Generator</h3>
    <p>Describe the project in plain language and let AI draft the line items.</p>
  </a>
</div>

<div class="section-head" style="text-align:start;margin-bottom:20px;">
  <h2 style="font-size:19px;">Start from a template</h2>
  <p style="color:var(--muted);font-size:14px;">Select a template to start creating your estimate. All templates are customizable to help you get quotes out quickly.</p>
</div>

<h3 style="font-size:14px;color:var(--muted);margin-bottom:14px;">BuildXact Saudi templates (<?= count($templates) ?>)</h3>

<div class="grid grid-3">
  <?php foreach ($templates as $t): ?>
    <a href="/app/estimates/templates/<?= $t['id'] ?>" class="card feature-card" style="text-decoration:none;color:inherit;">
      <div class="icon"><?= View::e($t['icon']) ?></div>
      <h3><?= View::e(View::local($t, 'name_en', 'name_ar')) ?></h3>
      <p><?= View::e(View::local($t, 'description_en', 'description_ar')) ?></p>
    </a>
  <?php endforeach; ?>
</div>
