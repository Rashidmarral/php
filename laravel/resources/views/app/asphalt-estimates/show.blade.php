@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>🛣️ <?= e($estimate['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?php if (!empty($estimate['city'])): ?><?= e($estimate['city']) ?> · <?php endif; ?>
      <?= t('common.client') ?>: <?= e($client['name'] ?? '—') ?>
      <?php if ($project): ?> · <?= t('common.project') ?>: <?= e($project['name']) ?><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/asphalt-estimates/<?= $estimate['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <a href="/app/asphalt-estimates/<?= $estimate['id'] ?>/drawing" class="btn btn-outline">📐 <?= t('user.asphalt_estimates.download_site_plan') ?></a>
    <form method="post" action="/app/asphalt-estimates/<?= $estimate['id'] ?>/delete" onsubmit="return confirm('<?= t('user.asphalt_estimates.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('user.asphalt_estimates.total_area') ?></div><div class="value"><?= e(number_format((float) $estimate['total_area_m2'], 2)) ?> m²</div></div>
  <div class="kpi"><div class="label"><?= t('user.asphalt_estimates.total_tons') ?></div><div class="value"><?= e(number_format((float) $estimate['total_asphalt_tons'], 2)) ?> t</div></div>
  <div class="kpi"><div class="label"><?= t('common.total') ?></div><div class="value"><?= money((float) $estimate['total']) ?></div></div>
</div>

<div class="card" style="margin-top:18px;">
  <h3><?= t('user.asphalt_estimates.segments') ?></h3>
  <div style="overflow-x:auto;">
  <table class="data">
    <thead><tr>
      <th><?= t('user.asphalt_estimates.col_segment_name') ?></th>
      <th><?= t('user.asphalt_estimates.col_length') ?></th>
      <th><?= t('user.asphalt_estimates.col_width') ?></th>
      <th><?= t('user.asphalt_estimates.col_area') ?></th>
      <th><?= t('user.asphalt_estimates.col_subbase_cost') ?></th>
      <th><?= t('user.asphalt_estimates.col_base_cost') ?></th>
      <th><?= t('user.asphalt_estimates.col_asphalt_cost') ?></th>
      <th><?= t('user.asphalt_estimates.col_segment_total') ?></th>
    </tr></thead>
    <tbody>
      <?php foreach ($segments as $s): ?>
        <tr>
          <td><?= e($s['name']) ?></td>
          <td><?= e($s['length_m']) ?></td>
          <td><?= e($s['width_m']) ?></td>
          <td><?= e($s['area_m2']) ?></td>
          <td><?= money((float) $s['subbase_cost']) ?></td>
          <td><?= money((float) $s['base_course_cost']) ?></td>
          <td><?= money((float) $s['asphalt_cost']) ?></td>
          <td><strong><?= money((float) $s['segment_total']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card" style="margin-top:18px;max-width:420px;">
  <h3><?= t('user.asphalt_estimates.other_costs') ?></h3>
  <div class="breakdown" style="font-size:13.5px;">
    <div><span><?= t('user.asphalt_estimates.segments_subtotal') ?></span><span><?= money((float) $estimate['segments_subtotal']) ?></span></div>
    <div><span><?= t('user.asphalt_estimates.other_costs_subtotal') ?></span><span><?= money((float) $estimate['other_costs_subtotal']) ?></span></div>
    <div><span><?= t('qe.subtotal') ?></span><span><?= money((float) $estimate['subtotal']) ?></span></div>
    <div><span><?= t('common.margin') ?> (<?= e($estimate['markup_percent']) ?>%)</span><span><?= money((float) $estimate['markup_amount']) ?></span></div>
    <div><span><?= t('user.asphalt_estimates.tax_rate') ?> (<?= e($estimate['tax_percent']) ?>%)</span><span><?= money((float) $estimate['tax_amount']) ?></span></div>
  </div>
  <div class="total-row" style="margin-top:8px;"><?= t('common.total') ?>: <?= money((float) $estimate['total']) ?></div>
</div>

<form method="post" action="/app/asphalt-estimates/<?= $estimate['id'] ?>/convert" style="margin-top:16px;" onsubmit="return confirm('<?= t('user.asphalt_estimates.convert_confirm') ?>');">
  <?= csrf_field() ?>
  <button type="submit" class="btn btn-outline">🧾 <?= t('user.asphalt_estimates.convert_to_estimate') ?></button>
</form>

<div class="card" style="margin-top:18px;">
  <h3>📐 <?= t('user.asphalt_estimates.site_plan_preview') ?></h3>
  <?php if (! empty($layout['hasLayout'])): ?>
    <div style="overflow-x:auto;">
      @include('app.asphalt-estimates._site-plan', ['layout' => $layout, 'estimate' => $estimate])
    </div>
  <?php else: ?>
    <p class="help-text"><?= t('user.asphalt_estimates.site_plan_empty') ?></p>
  <?php endif; ?>
</div>

@endsection
