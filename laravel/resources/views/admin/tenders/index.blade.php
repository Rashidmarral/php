@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.tenders.title') ?></h1>
  <a href="/admin/tenders/create" class="btn btn-primary">+ <?= t('admin.tenders.new') ?></a>
</div>
<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('admin.tenders.hint') ?></p>

<?php if ($tenders->isEmpty()): ?>
  <div class="empty-state card">
    <div class="icon">📋</div>
    <p><?= t('admin.tenders.none_yet') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.title') ?></th><th><?= t('admin.tenders.entity_col') ?></th><th><?= t('common.category') ?></th><th><?= t('admin.tenders.deadline_col') ?></th><th><?= t('common.value') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($tenders as $tItem): $isExpired = $tItem->isExpired(); ?>
      <tr>
        <td><?= e($tItem->title_en) ?><br><span class="help-text" dir="rtl"><?= e($tItem->title_ar) ?></span></td>
        <td><?= e($tItem->entity_name_en) ?></td>
        <td><span class="badge badge-blue"><?= e($categories[$tItem->category] ?? $tItem->category) ?></span></td>
        <td>
          <?= e($tItem->submission_deadline?->format('Y-m-d') ?? '—') ?>
          <?php if ($isExpired): ?><br><span class="badge badge-red"><?= t('admin.tenders.closed') ?></span><?php endif; ?>
        </td>
        <td><?= $tItem->estimated_value_sar !== null ? money((float) $tItem->estimated_value_sar) : '—' ?></td>
        <td><span class="badge badge-<?= $tItem->is_active ? 'green' : 'gray' ?>"><?= $tItem->is_active ? t('common.active') : t('admin.tenders.inactive') ?></span></td>
        <td style="display:flex;gap:6px;">
          <a href="/admin/tenders/<?= $tItem->id ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <form method="post" action="/admin/tenders/<?= $tItem->id ?>/delete" onsubmit="return confirm('<?= t('admin.tenders.delete_confirm') ?>');" style="display:inline;">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
