@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.consultations.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.consultations.hint') ?></p>

<?php $statusLabels = ['requested' => t('admin.consultations.status_requested'), 'scheduled' => t('admin.consultations.status_scheduled'), 'completed' => t('admin.consultations.status_completed'), 'cancelled' => t('admin.consultations.status_cancelled')]; ?>

<div class="tabs" style="margin-bottom:20px;">
  <a href="/admin/consultations" class="<?= $statusFilter === '' ? 'active' : '' ?>"><?= t('common.all') ?></a>
  <?php foreach (\App\Models\Consultation::STATUSES as $s): ?>
    <a href="/admin/consultations?status=<?= $s ?>" class="<?= $statusFilter === $s ? 'active' : '' ?>"><?= $statusLabels[$s] ?? ucfirst($s) ?></a>
  <?php endforeach; ?>
</div>

<table class="data">
  <thead><tr><th><?= t('admin.consultations.requested') ?></th><th><?= t('common.company') ?></th><th><?= t('admin.consultations.requested_by') ?></th><th><?= t('admin.consultations.format') ?></th><th><?= t('admin.consultations.topic') ?></th><th><?= t('common.status') ?></th><th><?= t('admin.consultations.engineer') ?></th><th><?= t('admin.consultations.scheduled') ?></th><?php if (auth()->user()->isSuperAdmin()): ?><th></th><?php endif; ?></tr></thead>
  <tbody>
  <?php foreach ($consultations as $c): $fid = 'con-' . $c['id']; ?>
    <?php if (auth()->user()->isSuperAdmin()): ?>
      <form id="<?= $fid ?>" method="post" action="/admin/consultations/<?= $c['id'] ?>/update"><?= csrf_field() ?></form>
    <?php endif; ?>
    <tr>
      <td class="help-text" style="white-space:nowrap;"><?= e($c['created_at']) ?></td>
      <td><a href="/admin/companies/<?= $c['company_id'] ?>"><?= e($c['company_name']) ?></a></td>
      <td><?= e($c['requested_by_name'] ?? '—') ?></td>
      <td><?= $c['type'] === 'in_person' ? '🚗 ' . t('admin.consultations.in_person') : '💬 ' . t('admin.consultations.chat_video') ?></td>
      <td style="max-width:220px;"><?= e($c['topic']) ?><?php if ($c['notes']): ?><div class="help-text"><?= e($c['notes']) ?></div><?php endif; ?></td>
      <td>
        <?php if (auth()->user()->isSuperAdmin()): ?>
          <select form="<?= $fid ?>" name="status" style="width:120px;">
            <?php foreach (\App\Models\Consultation::STATUSES as $s): ?>
              <option value="<?= $s ?>" <?= $c['status'] === $s ? 'selected' : '' ?>><?= $statusLabels[$s] ?? ucfirst($s) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="badge badge-gray"><?= $statusLabels[$c['status']] ?? ucfirst($c['status']) ?></span>
        <?php endif; ?>
      </td>
      <td><?php if (auth()->user()->isSuperAdmin()): ?><input form="<?= $fid ?>" type="text" name="assigned_engineer" value="<?= e($c['assigned_engineer'] ?? '') ?>" style="width:130px;" placeholder="<?= t('admin.consultations.engineer_name') ?>"><?php else: ?><?= e($c['assigned_engineer'] ?: '—') ?><?php endif; ?></td>
      <td><?php if (auth()->user()->isSuperAdmin()): ?><input form="<?= $fid ?>" type="datetime-local" name="scheduled_at" value="<?= e($c['scheduled_at'] ?? '') ?>" style="width:170px;"><?php else: ?><?= e($c['scheduled_at'] ?: '—') ?><?php endif; ?></td>
      <?php if (auth()->user()->isSuperAdmin()): ?>
        <td><button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button></td>
      <?php endif; ?>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($consultations)): ?>
    <tr><td colspan="9" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.consultations.none_yet') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
@include('admin.partials.pagination', ['paginator' => $consultations])

@endsection
