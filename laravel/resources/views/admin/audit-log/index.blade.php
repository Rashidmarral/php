@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.audit.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.audit.hint') ?></p>

<form method="get" action="/admin/audit-log" class="card" style="margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.audit.admin') ?></label>
    <input type="text" name="admin" value="<?= e($adminFilter) ?>" placeholder="<?= t('admin.audit.name_contains') ?>">
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.audit.action') ?></label>
    <select name="action">
      <option value=""><?= t('admin.audit.all_actions') ?></option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $a)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.filter') ?></button>
  <?php if ($adminFilter !== '' || $actionFilter !== ''): ?>
    <a href="/admin/audit-log" class="btn btn-light"><?= t('common.clear') ?></a>
  <?php endif; ?>
</form>

<table class="data">
  <thead><tr><th><?= t('admin.audit.when') ?></th><th><?= t('admin.audit.admin') ?></th><th><?= t('admin.audit.action') ?></th><th><?= t('admin.audit.target') ?></th><th><?= t('admin.audit.details') ?></th><th><?= t('admin.audit.ip') ?></th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="help-text" style="white-space:nowrap;"><?= e($l['created_at']) ?></td>
      <td><?= e($l['admin_name'] ?: '—') ?></td>
      <td><span class="badge badge-gray"><?= e(str_replace('_', ' ', $l['action'])) ?></span></td>
      <td class="help-text"><?= e($l['target_type'] ? ($l['target_type'] . ' #' . $l['target_id']) : '—') ?></td>
      <td><?= e($l['details'] ?? '') ?></td>
      <td class="help-text"><?= e($l['ip_address'] ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($logs)): ?>
    <tr><td colspan="6" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.audit.no_activity') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>

@endsection
