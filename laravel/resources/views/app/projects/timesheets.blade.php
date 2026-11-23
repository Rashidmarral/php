@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.timesheets.title') ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.timesheets.hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <form method="get" action="/app/projects/<?= $project['id'] ?>/timesheets" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <div class="form-group" style="margin:0;"><label><?= t('user.timesheets.from_date') ?></label><input type="date" name="from" value="<?= e($from ?? '') ?>"></div>
    <div class="form-group" style="margin:0;"><label><?= t('user.timesheets.to_date') ?></label><input type="date" name="to" value="<?= e($to ?? '') ?>"></div>
    <button type="submit" class="btn btn-outline"><?= t('common.filter') ?></button>
  </form>
</div>

<div class="kpi-grid" style="grid-template-columns:repeat(2,1fr);margin-bottom:24px;max-width:440px;">
  <div class="kpi"><div class="label"><?= t('user.timesheets.total_hours') ?></div><div class="value" style="font-size:19px;"><?= number_format($totalHours, 2) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.timesheets.total_cost') ?></div><div class="value" style="font-size:19px;"><?= money($totalCost) ?></div></div>
</div>

<div class="card">
  <h3 style="font-size:14px;"><?= t('user.timesheets.add_entry') ?></h3>
  <form method="post" action="/app/projects/<?= $project['id'] ?>/timesheets" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;min-width:180px;">
      <label><?= t('user.timesheets.worker') ?></label>
      <select name="user_id" required>
        <option value=""><?= t('user.timesheets.select_worker') ?></option>
        <?php foreach ($teamMembers as $member): ?><option value="<?= $member['id'] ?>"><?= e($member['name']) ?><?= $member['hourly_rate'] === null ? ' (' . t('user.timesheets.rate_not_set') . ')' : '' ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('common.date') ?></label><input type="date" name="work_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;width:110px;"><label><?= t('user.timesheets.hours') ?></label><input type="number" step="0.25" min="0.25" max="24" name="hours" required></div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('user.timesheets.add_entry_btn') ?></button>
  </form>
</div>

<?php if (empty($entries)): ?>
  <div class="empty-state card">
    <div class="icon">🕒</div>
    <p><?= t('user.timesheets.none_yet') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.date') ?></th><th><?= t('user.timesheets.worker') ?></th><th><?= t('user.timesheets.hours') ?></th><th><?= t('common.cost') ?></th><th><?= t('common.notes') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($entries as $entry): ?>
      <tr>
        <td><?= e($entry['work_date']) ?></td>
        <td><?= e($entry['worker_name']) ?></td>
        <td><?= number_format((float) $entry['hours'], 2) ?></td>
        <td><?php if ($entry['cost'] !== null): ?><?= money((float) $entry['cost']) ?><?php else: ?><span class="badge badge-gray"><?= t('user.timesheets.rate_not_set') ?></span><?php endif; ?></td>
        <td><?= e($entry['notes'] ?? '') ?></td>
        <td>
          <form method="post" action="/app/timesheets/<?= $entry['id'] ?>/delete" onsubmit="return confirm('<?= t('user.timesheets.remove_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('common.remove') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
