@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.safety_incidents.title') ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.safety_incidents.hint') ?></p>

<?php if (empty($incidents)): ?>
  <div class="empty-state card">
    <div class="icon">⚠️</div>
    <p><?= t('user.safety_incidents.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th>#</th>
          <th><?= t('user.safety_incidents.type') ?></th>
          <th><?= t('user.safety_incidents.severity') ?></th>
          <th><?= t('common.date') ?></th>
          <th><?= t('user.punch_list.location') ?></th>
          <th><?= t('user.safety_incidents.injured_person') ?></th>
          <th><?= t('common.status') ?></th>
          <th><?= t('common.description') ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($incidents as $incident):
        $severityBadge = ['low' => 'gray', 'medium' => 'yellow', 'high' => 'red', 'critical' => 'red'][$incident['severity']] ?? 'gray';
        $incidentStatusBadge = ['open' => 'red', 'under_investigation' => 'yellow', 'closed' => 'green'][$incident['status']] ?? 'gray';
      ?>
        <tr>
          <td>INC-<?= str_pad((string)$incident['incident_number'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($incident['incident_type']) ?></td>
          <td><span class="badge badge-<?= $severityBadge ?>"><?= e($severities[$incident['severity']] ?? ucfirst($incident['severity'])) ?></span></td>
          <td><?= e($incident['incident_date']) ?></td>
          <td><?= e($incident['location'] ?: '—') ?></td>
          <td><?= e($incident['injured_person_name'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $incidentStatusBadge ?>"><?= e($statuses[$incident['status']] ?? ucfirst($incident['status'])) ?></span></td>
          <td><?= e(\Illuminate\Support\Str::limit((string)$incident['description'], 80)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>
<?php endif; ?>

@endsection
