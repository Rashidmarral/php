<?php use App\Core\View; use App\Core\Csrf; use App\Core\Lang; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($estimate['project_name'] ?: ('Quick Estimate #' . $estimate['id'])) ?></h1>
    <p class="help-text" style="margin-top:4px;">Client: <?= View::e($client['name'] ?? '—') ?> · Generated <?= View::e($estimate['created_at']) ?></p>
  </div>
  <form method="post" action="/app/quick-estimate/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('Delete this quick estimate?');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-danger">Delete</button>
  </form>
</div>

<div class="card" style="max-width:640px;text-align:center;padding:40px;">
  <div style="font-size:40px;">✅</div>
  <div style="font-size:40px;font-weight:800;color:var(--brand-dark);margin:10px 0;">
    <?= View::money((float)$estimate['total']) ?>
  </div>
  <p class="help-text">
    <?= t('qe.subtotal') ?>: <?= View::money((float)$estimate['subtotal']) ?> ·
    <?= t('qe.vat', ['rate' => $vatRate]) ?>: <?= View::money((float)$estimate['vat_amount']) ?>
  </p>

  <table class="data" style="text-align:start;margin-top:24px;">
    <tbody>
      <?php if ($region): ?><tr><td><?= t('qe.region') ?></td><td><?= View::e(Lang::locale() === 'ar' ? $region['name_ar'] : $region['name_en']) ?></td></tr><?php endif; ?>
      <?php if ($foundation): ?><tr><td><?= t('qe.foundation_type') ?></td><td><?= View::e(Lang::locale() === 'ar' ? $foundation['name_ar'] : $foundation['name_en']) ?></td></tr><?php endif; ?>
      <tr><td><?= t('qe.total_area') ?></td><td><?= View::e((string)$estimate['total_area']) ?> m²</td></tr>
      <?php if (!empty($addons)): ?>
      <tr><td><?= t('qe.addons') ?></td><td><?= View::e(implode(', ', array_map(fn($a) => $a[Lang::locale() === 'ar' ? 'name_ar' : 'name_en'], $addons))) ?></td></tr>
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
      </select>
    </div>
    <button type="submit" class="btn btn-primary">⬇ <?= t('qe.download_pdf') ?></button>
  </form>

  <form method="post" action="/app/quick-estimate/<?= $estimate['id'] ?>/convert" style="margin-top:12px;" onsubmit="return confirm('Convert this into a full estimate you can track and invoice against?');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-outline">Convert to full estimate →</button>
  </form>
</div>
