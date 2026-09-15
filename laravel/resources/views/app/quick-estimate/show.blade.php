@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($estimate['project_name'] ?: ('Quick Estimate #' . $estimate['id'])) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client['name'] ?? '—') ?> · <?= t('user.quick_estimate.generated') ?> <?= e($estimate['created_at']) ?></p>
  </div>
  <form method="post" action="/app/quick-estimate/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('<?= t('user.quick_estimate.delete_confirm') ?>');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
  </form>
</div>

<div class="card" style="max-width:640px;text-align:center;padding:40px;">
  <div style="font-size:40px;">✅</div>
  <div style="font-size:40px;font-weight:800;color:var(--brand-dark);margin:10px 0;">
    <?= money((float)$estimate['total']) ?>
  </div>
  <p class="help-text">
    <?= t('qe.subtotal') ?>: <?= money((float)$estimate['subtotal']) ?> ·
    <?= t('qe.vat', ['rate' => $vatRate]) ?>: <?= money((float)$estimate['vat_amount']) ?>
  </p>

  <table class="data" style="text-align:start;margin-top:24px;">
    <tbody>
      <?php if ($region): ?><tr><td><?= t('qe.region') ?></td><td><?= e(app()->getLocale() === 'ar' ? $region['name_ar'] : $region['name_en']) ?></td></tr><?php endif; ?>
      <?php if ($foundation): ?><tr><td><?= t('qe.foundation_type') ?></td><td><?= e(app()->getLocale() === 'ar' ? $foundation['name_ar'] : $foundation['name_en']) ?></td></tr><?php endif; ?>
      <?php if ($qualityTier): ?><tr><td><?= t('qe.quality_tier') ?></td><td><?= e(app()->getLocale() === 'ar' ? $qualityTier['name_ar'] : $qualityTier['name_en']) ?></td></tr><?php endif; ?>
      <tr><td><?= t('qe.total_area') ?></td><td><?= e((string)$estimate['total_area']) ?> m²</td></tr>
      <?php if (!empty($addons)): ?>
      <tr><td><?= t('qe.addons') ?></td><td><?= e(implode(', ', array_map(fn($a) => $a[app()->getLocale() === 'ar' ? 'name_ar' : 'name_en'], $addons))) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <form method="get" action="/app/quick-estimate/<?= $estimate['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;justify-content:center;align-items:end;margin-top:24px;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;">
      <select name="template">
        <option value="modern">Modern</option>
        <option value="classic">Classic</option>
        <option value="minimal">Minimal</option>
        <option value="bold">Bold</option>
        <option value="elegant">Elegant</option>
        <option value="saudi">Saudi (ZATCA bilingual)</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">⬇ <?= t('qe.download_pdf') ?></button>
  </form>

  <form method="post" action="/app/quick-estimate/<?= $estimate['id'] ?>/convert" style="margin-top:12px;" onsubmit="return confirm('<?= t('user.quick_estimate.convert_confirm') ?>');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-outline"><?= t('user.quick_estimate.convert_to_full') ?></button>
  </form>
</div>

@endsection
