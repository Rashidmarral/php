@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>🕌 <?= t('user.zakat.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.zakat.intro_hint') ?></p>
  </div>
  <a href="/app/zakat/create" class="btn btn-primary"><?= t('user.zakat.new_estimate') ?></a>
</div>

<div class="alert" style="background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
  ⚠️ <?= t('user.zakat.disclaimer') ?>
</div>

<?php if ($calculations->isEmpty()): ?>
  <div class="card empty-state">
    <div class="icon">🕌</div>
    <h3><?= t('user.zakat.none_title') ?></h3>
    <p><?= t('user.zakat.none_hint') ?></p>
    <a href="/app/zakat/create" class="btn btn-primary" style="margin-top:12px;"><?= t('user.zakat.new_estimate') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead>
      <tr>
        <th><?= t('user.zakat.period_end_date') ?></th>
        <th><?= t('user.zakat.rate_type') ?></th>
        <th><?= t('user.zakat.zakat_base') ?></th>
        <th><?= t('user.zakat.zakat_due') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($calculations as $c): ?>
        <tr>
          <td><?= e($c->period_end_date->format('Y-m-d')) ?></td>
          <td><?= t('user.zakat.rate_' . $c->rate_type) ?></td>
          <td><?= money((float) $c->zakat_base) ?></td>
          <td><strong><?= money((float) $c->zakat_due) ?></strong></td>
          <td><a href="/app/zakat/<?= $c->id ?>" class="btn btn-outline btn-sm"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
