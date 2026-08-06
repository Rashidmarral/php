<?php use App\Core\View; use App\Core\Lang; ?>
<section class="section" style="padding-top:56px;">
  <div class="container" style="max-width:720px;">
    <div class="card" style="text-align:center;padding:40px;">
      <div style="font-size:40px;">✅</div>
      <h1 style="margin-top:10px;"><?= t('qe.total') ?></h1>
      <div style="font-size:40px;font-weight:800;color:var(--brand-dark);margin:10px 0;">
        <?= number_format((float)$estimate['total'], 2) ?> SAR
      </div>
      <p class="help-text">
        <?= t('qe.subtotal') ?>: <?= number_format((float)$estimate['subtotal'], 2) ?> SAR ·
        <?= t('qe.vat', ['rate' => $vatRate]) ?>: <?= number_format((float)$estimate['vat_amount'], 2) ?> SAR
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

      <form method="get" action="/quick-estimate/<?= $estimate['id'] ?>/pdf" target="_blank" style="display:flex;gap:8px;justify-content:center;align-items:end;margin-top:24px;">
        <div class="form-group" style="margin:0;">
          <select name="template">
            <option value="modern">Modern</option>
            <option value="classic">Classic</option>
            <option value="minimal">Minimal</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary">⬇ <?= t('qe.download_pdf') ?></button>
        <a href="/quick-estimate" class="btn btn-light"><?= t('qe.start_over') ?></a>
      </form>
    </div>

    <div class="card" style="text-align:center;margin-top:24px;padding:32px;">
      <h2><?= t('cta.title') ?></h2>
      <p style="color:var(--muted);">Turn this estimate into a full project with budgets, scheduling, and invoicing.</p>
      <a href="/register" class="btn btn-primary"><?= t('hero.cta_primary') ?></a>
    </div>
  </div>
</section>
