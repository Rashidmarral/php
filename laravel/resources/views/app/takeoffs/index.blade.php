@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.takeoffs.title') ?></h1>
  <a href="/app/takeoffs/create" class="btn btn-primary"><?= t('user.takeoffs.new') ?></a>
</div>

<?php if (empty($takeoffs)): ?>
  <div class="card empty-state">
    <div class="icon">📐</div>
    <h3><?= t('user.takeoffs.no_takeoffs_title') ?></h3>
    <p><?= t('user.takeoffs.no_takeoffs_hint') ?></p>
    <a href="/app/takeoffs/create" class="btn btn-primary"><?= t('user.takeoffs.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.project') ?></th><th><?= t('user.takeoffs.created_col') ?></th></tr></thead>
    <tbody>
    <?php foreach ($takeoffs as $t): ?>
      <tr>
        <td><a href="/app/takeoffs/<?= $t['id'] ?>"><?= e($t['name']) ?></a></td>
        <td><?= e($t['project_name'] ?? '—') ?></td>
        <td class="help-text"><?= e($t['created_at']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
