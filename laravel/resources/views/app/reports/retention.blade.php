@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit"><?= t('user.reports.tab_profit') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention" class="active"><?= t('user.reports.tab_retention') ?></a>
</div>

<p class="help-text" style="max-width:820px;margin-bottom:16px;"><?= t('user.reports.retention_hint') ?></p>

<div class="kpi-grid" style="grid-template-columns:repeat(2,220px);">
  <div class="kpi"><div class="label"><?= t('user.reports.outstanding_retention') ?></div><div class="value"><?= money($outstanding) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.reports.released') ?></div><div class="value"><?= money($released) ?></div></div>
</div>

<?php if (empty($rows)): ?>
  <div class="card empty-state">
    <div class="icon">🔒</div>
    <h3><?= t('user.reports.no_retention_title') ?></h3>
    <p><?= t('user.reports.no_retention_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('user.reports.invoice_col') ?></th><th><?= t('common.client') ?></th><th><?= t('user.reports.retention_percent_col') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/app/invoices/<?= $r['id'] ?>"><?= e($r['invoice_number']) ?></a></td>
        <td><?= e($r['client_name'] ?? '—') ?></td>
        <td><?= e((string)$r['retention_percent']) ?>%</td>
        <td><?= money((float)$r['retention_amount']) ?></td>
        <td>
          <?php if ($r['retention_released']): ?>
            <span class="badge badge-green"><?= t('user.reports.released') ?> <?= e($r['retention_released_at']) ?></span>
          <?php else: ?>
            <span class="badge badge-yellow"><?= t('user.reports.outstanding') ?></span>
          <?php endif; ?>
        </td>
        <td>
          <?php if (!$r['retention_released']): ?>
            <form method="post" action="/app/invoices/<?= $r['id'] ?>/release-retention" onsubmit="return confirm('<?= t('user.reports.mark_released_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.reports.mark_released') ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
