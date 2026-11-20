@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.toolbox_talks.title') ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.toolbox_talks.hint') ?></p>

<?php if (empty($talks)): ?>
  <div class="empty-state card">
    <div class="icon">🦺</div>
    <p><?= t('user.toolbox_talks.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('common.date') ?></th>
          <th><?= t('user.toolbox_talks.topic') ?></th>
          <th><?= t('user.toolbox_talks.attendee_count') ?></th>
          <th><?= t('user.toolbox_talks.notes') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($talks as $talk): ?>
        <tr>
          <td><?= e($talk['talk_date']) ?></td>
          <td><?= e($talk['topic']) ?></td>
          <td><?= $talk['attendee_count'] !== null ? e((string)$talk['attendee_count']) : '—' ?></td>
          <td><?= e(\Illuminate\Support\Str::limit((string)($talk['notes'] ?? ''), 80)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

@endsection
