@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.settings.title') ?></h1>
</div>

@include('app.settings.partials.tabs', ['active' => 'invoice_templates'])

<div class="section-head" style="text-align:start;margin-bottom:16px;">
  <h2 style="font-size:19px;"><?= t('user.settings.invoice_templates_heading') ?></h2>
  <p style="color:var(--muted);font-size:14px;max-width:720px;"><?= t('user.settings.invoice_templates_hint') ?></p>
</div>

<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:24px;">
  <?php foreach ($documentTypes as $type): ?>
    <a href="/app/settings/invoice-templates/<?= e($type) ?>"
       style="padding:7px 14px;border-radius:20px;font-size:13px;font-weight:600;text-decoration:none;border:1px solid <?= $type === $documentType ? 'var(--brand)' : 'var(--border)' ?>;background:<?= $type === $documentType ? 'var(--brand)' : '#fff' ?>;color:<?= $type === $documentType ? '#fff' : 'var(--muted)' ?>;">
      <?= t('pdf.doctype.' . $type) ?>
    </a>
  <?php endforeach; ?>
</div>

<div style="margin-bottom:32px;">
  <h3 style="font-size:15px;margin-bottom:4px;"><?= t('user.settings.your_templates_heading') ?></h3>
  <?php if ($templates->isEmpty()): ?>
    <p class="help-text"><?= t('user.settings.no_templates_for_type') ?></p>
  <?php endif; ?>
  <div style="display:flex;flex-wrap:wrap;gap:20px;">
    <?php foreach ($templates as $tpl): ?>
      <div class="card" style="width:270px;padding:14px;<?= $tpl->is_default ? 'border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-light);' : '' ?>">
        <div style="width:238px;height:210px;overflow:hidden;border:1px solid var(--border);border-radius:8px;position:relative;background:#fff;margin:0 auto;">
          <iframe srcdoc="<?= e($templatePreviews[$tpl->id]) ?>" style="width:794px;height:1123px;border:0;position:absolute;top:0;left:0;transform:scale(0.3);transform-origin:top left;" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
        </div>

        <div style="margin-top:12px;">
          <div style="display:flex;justify-content:space-between;align-items:start;gap:6px;">
            <h3 style="font-size:15px;margin:0;"><?= e(app()->getLocale() === 'ar' && $tpl->name_ar ? $tpl->name_ar : $tpl->name) ?></h3>
            <?php if ($tpl->is_default): ?>
              <span class="badge badge-green" style="white-space:nowrap;"><?= t('user.settings.default_badge') ?></span>
            <?php endif; ?>
          </div>
          <p class="help-text" style="margin-top:6px;"><?= t('user.settings.layout_label') ?>: <?= t('user.settings.layout_option_' . $tpl->layout) ?></p>
        </div>

        <?php if ($canManage): ?>
          <a href="/app/settings/invoice-templates/template/<?= $tpl->id ?>/edit" class="btn btn-outline" style="width:100%;margin-top:10px;display:block;text-align:center;"><?= t('common.edit') ?></a>
          <div style="display:flex;gap:8px;margin-top:8px;">
            <?php if (!$tpl->is_default): ?>
              <form method="post" action="/app/settings/invoice-templates/template/<?= $tpl->id ?>/default" style="flex:1;margin:0;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-light btn-sm" style="width:100%;"><?= t('user.settings.set_default') ?></button>
              </form>
            <?php endif; ?>
            <form method="post" action="/app/settings/invoice-templates/template/<?= $tpl->id ?>/delete" style="flex:1;margin:0;" onsubmit="return confirm('<?= t('user.settings.delete_template_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-light btn-sm" style="width:100%;color:var(--danger);"><?= t('common.delete') ?></button>
            </form>
          </div>
        <?php else: ?>
          <p class="help-text" style="margin-top:10px;"><?= t('user.settings.manage_settings_required_hint') ?></p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div>
  <h3 style="font-size:15px;margin-bottom:4px;"><?= t('user.settings.starter_presets_heading') ?></h3>
  <p class="help-text" style="margin-bottom:14px;"><?= t('user.settings.starter_presets_hint') ?></p>
  <div style="display:flex;flex-wrap:wrap;gap:20px;">
    <?php foreach ($presets as $key => $preset): ?>
      <div class="card" style="width:250px;padding:12px;">
        <div style="width:226px;height:200px;overflow:hidden;border:1px solid var(--border);border-radius:8px;position:relative;background:#fff;margin:0 auto;">
          <iframe srcdoc="<?= e($presetPreviews[$key]) ?>" style="width:794px;height:1123px;border:0;position:absolute;top:0;left:0;transform:scale(0.284);transform-origin:top left;" tabindex="-1" aria-hidden="true" scrolling="no"></iframe>
        </div>
        <div style="margin-top:10px;">
          <h4 style="font-size:14px;margin:0;"><?= e(app()->getLocale() === 'ar' ? $preset['name_ar'] : $preset['name']) ?></h4>
          <p class="help-text" style="margin-top:4px;"><?= e(app()->getLocale() === 'ar' ? $preset['description_ar'] : $preset['description']) ?></p>
        </div>
        <?php if ($canManage): ?>
          <form method="post" action="/app/settings/invoice-templates/use-preset" style="margin-top:10px;">
            <?= csrf_field() ?>
            <input type="hidden" name="preset" value="<?= e($key) ?>">
            <input type="hidden" name="document_type" value="<?= e($documentType) ?>">
            <button type="submit" class="btn btn-outline" style="width:100%;"><?= t('user.settings.use_preset') ?></button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
</div>

@endsection
