@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.activity_log.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('user.activity_log.hint') ?></p>

<form method="get" action="/app/activity-log" class="card" style="margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label><?= t('user.activity_log.user') ?></label>
    <input type="text" name="user" value="<?= e($userFilter) ?>" placeholder="<?= t('user.activity_log.name_contains') ?>">
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('user.activity_log.action') ?></label>
    <select name="action">
      <option value=""><?= t('user.activity_log.all_actions') ?></option>
      <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $actionFilter === $a ? 'selected' : '' ?>><?= e(str_replace('_', ' ', $a)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.filter') ?></button>
  <?php if ($userFilter !== '' || $actionFilter !== ''): ?>
    <a href="/app/activity-log" class="btn btn-light"><?= t('common.clear') ?></a>
  <?php endif; ?>
</form>

<table class="data">
  <thead><tr><th><?= t('user.activity_log.when') ?></th><th><?= t('user.activity_log.user') ?></th><th><?= t('user.activity_log.action') ?></th><th><?= t('user.activity_log.target') ?></th><th><?= t('user.activity_log.details') ?></th></tr></thead>
  <tbody>
  <?php foreach ($logs as $l): ?>
    <tr>
      <td class="help-text" style="white-space:nowrap;"><?= e($l->created_at) ?></td>
      <td><?= e($l->admin_name ?: '—') ?></td>
      <td><span class="badge badge-gray"><?= e(str_replace('_', ' ', $l->action)) ?></span></td>
      <td class="help-text"><?= e($l->target_type ? ($l->target_type . ' #' . $l->target_id) : '—') ?></td>
      <td><?= e($l->details ?? '') ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($logs->isEmpty()): ?>
    <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;"><?= t('user.activity_log.no_activity') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
@include('app.partials.pagination', ['paginator' => $logs])

@endsection
