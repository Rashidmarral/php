@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.rfi.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/rfis/new" class="btn btn-primary"><?= t('user.rfi.new') ?></a>
</div>

<?php if ($rfis->isEmpty()): ?>
  <div class="empty-state card">
    <div class="icon">❓</div>
    <p><?= t('user.rfi.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th>#</th>
          <th><?= t('user.rfi.subject') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('user.rfi.due_date') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($rfis as $r):
        $rfiStatusBadge = ['open' => 'yellow', 'answered' => 'blue', 'closed' => 'green'][$r->status] ?? 'gray';
      ?>
        <tr>
          <td><?= e($r->displayNumber()) ?></td>
          <td><?= e($r->subject) ?></td>
          <td><span class="badge badge-<?= $rfiStatusBadge ?>"><?= e($statuses[$r->status] ?? ucfirst($r->status)) ?></span></td>
          <td><?= e($r->due_date ? $r->due_date->format('Y-m-d') : '—') ?></td>
          <td><a href="/app/rfis/<?= $r->id ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

@endsection
