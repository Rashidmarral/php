@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= t('user.rfqs.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.rfqs.hint') ?></p>
  </div>
  <a href="/app/rfqs/create" class="btn btn-primary"><?= t('user.rfqs.new') ?></a>
</div>

<?php if (empty($rfqs)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <h3><?= t('user.rfqs.no_rfqs_title') ?></h3>
    <p><?= t('user.rfqs.no_rfqs_hint') ?></p>
    <a href="/app/rfqs/create" class="btn btn-primary"><?= t('user.rfqs.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.project') ?></th><th><?= t('common.status') ?></th><th><?= t('user.rfqs.due_date') ?></th><th></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rfqs as $r): ?>
      <?php $statusBadge = ['draft' => 'gray', 'sent' => 'blue', 'comparing' => 'yellow', 'awarded' => 'green', 'cancelled' => 'red'][$r['status']] ?? 'gray'; ?>
      <tr>
        <td><a href="/app/rfqs/<?= $r['id'] ?>"><?= e($r['title']) ?></a></td>
        <td><?= e($r['project_name'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $statusBadge ?>"><?= e($statuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
        <td><?= e($r['due_date'] ?: '—') ?></td>
        <td><?= t('user.rfqs.quote_count', ['count' => $r['quote_count']]) ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/rfqs/<?= $r['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
