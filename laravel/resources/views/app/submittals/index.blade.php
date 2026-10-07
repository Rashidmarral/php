@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.submittals.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/submittals/new" class="btn btn-primary"><?= t('user.submittals.new') ?></a>
</div>

<?php if ($submittals->isEmpty()): ?>
  <div class="empty-state card">
    <div class="icon">📐</div>
    <p><?= t('user.submittals.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th>#</th>
          <th><?= t('common.title') ?></th>
          <th><?= t('user.submittals.spec_section') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('user.submittals.due_date') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($submittals as $s):
        $submittalStatusBadge = ['submitted' => 'gray', 'under_review' => 'yellow', 'approved' => 'green', 'approved_as_noted' => 'green', 'rejected' => 'red', 'revise_resubmit' => 'red'][$s->status] ?? 'gray';
      ?>
        <tr>
          <td><?= e($s->displayNumber()) ?></td>
          <td><?= e($s->title) ?></td>
          <td><?= e($s->spec_section ?: '—') ?></td>
          <td><span class="badge badge-<?= $submittalStatusBadge ?>"><?= e($statuses[$s->status] ?? ucfirst($s->status)) ?></span></td>
          <td><?= e($s->due_date ? $s->due_date->format('Y-m-d') : '—') ?></td>
          <td><a href="/app/submittals/<?= $s->id ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

@endsection
