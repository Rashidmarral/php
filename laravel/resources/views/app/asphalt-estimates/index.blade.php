@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>🛣️ <?= t('user.asphalt_estimates.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.asphalt_estimates.intro_hint') ?></p>
  </div>
  <a href="/app/asphalt-estimates/create" class="btn btn-primary"><?= t('user.asphalt_estimates.new_title') ?></a>
</div>

<?php if (empty($estimates)): ?>
  <div class="card empty-state">
    <div class="icon">🛣️</div>
    <h3><?= t('user.asphalt_estimates.none_title') ?></h3>
    <p><?= t('user.asphalt_estimates.none_hint') ?></p>
    <a href="/app/asphalt-estimates/create" class="btn btn-primary"><?= t('user.asphalt_estimates.new_title') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr>
      <th><?= t('common.name') ?></th>
      <th><?= t('common.city') ?></th>
      <th><?= t('common.project') ?></th>
      <th><?= t('common.status') ?></th>
      <th><?= t('common.total') ?></th>
      <th></th>
    </tr></thead>
    <tbody>
      <?php foreach ($estimates as $e): ?>
        <tr>
          <td><a href="/app/asphalt-estimates/<?= $e['id'] ?>"><?= e($e['name']) ?></a></td>
          <td><?= e($e['city'] ?: '—') ?></td>
          <td><?= e($e['project_name'] ?? '—') ?></td>
          <td><span class="badge badge-gray"><?= e($e['status']) ?></span></td>
          <td><?= money((float) $e['total']) ?></td>
          <td style="display:flex;gap:6px;">
            <a href="/app/asphalt-estimates/<?= $e['id'] ?>" class="btn btn-outline btn-sm"><?= t('common.view') ?></a>
            <a href="/app/asphalt-estimates/<?= $e['id'] ?>/edit" class="btn btn-outline btn-sm"><?= t('common.edit') ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
