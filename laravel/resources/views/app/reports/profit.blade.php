@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit" class="active"><?= t('user.reports.tab_profit') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
</div>

<p class="help-text" style="margin-bottom:16px;"><?= t('user.reports.profit_hint') ?></p>

<?php if (empty($rows)): ?>
  <div class="card empty-state"><div class="icon">📈</div><h3><?= t('user.reports.no_projects_title') ?></h3></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.project') ?></th><th><?= t('common.amount') ?></th><th><?= t('user.reports.invoiced_col') ?></th><th><?= t('user.reports.paid_col') ?></th><th><?= t('user.reports.profit_col') ?></th><th><?= t('common.margin') ?></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><a href="/app/projects/<?= $r['project']['id'] ?>"><?= e($r['project']['name']) ?></a></td>
        <td><?= money($r['budget']) ?></td>
        <td><?= money($r['invoiced']) ?></td>
        <td><?= money($r['paid']) ?></td>
        <td style="color:<?= $r['profit'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;font-weight:700;"><?= money($r['profit']) ?></td>
        <td><?= $r['margin'] === null ? '—' : $r['margin'] . '%' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
