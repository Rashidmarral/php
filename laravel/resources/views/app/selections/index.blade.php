@extends('layouts.app')

{{--
  Lists this project's Selections (allowance tracking) grouped by category — each row
  shows the budgeted allowance against the chosen option's price (or "—" while the
  client hasn't picked yet) and the resulting variance, color-coded the same way
  reports/profit.blade.php colors its own cost-variance column (over-allowance in
  var(--danger), under/on-allowance in var(--success)).
--}}
@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.selections.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/selections/create" class="btn btn-primary"><?= t('user.selections.new') ?></a>
</div>

<?php if (empty($grouped)): ?>
  <div class="card empty-state">
    <div class="icon">🎨</div>
    <h3><?= t('user.selections.no_selections_title') ?></h3>
    <p><?= t('user.selections.no_selections_hint') ?></p>
  </div>
<?php else: ?>
  <?php foreach ($grouped as $category => $rows): ?>
    <div class="card" style="margin-bottom:20px;">
      <h3><?= e($category) ?></h3>
      <div style="overflow-x:auto;">
      <table class="data">
        <thead>
          <tr>
            <th><?= t('common.name') ?></th>
            <th><?= t('user.selections.allowance') ?></th>
            <th><?= t('user.selections.chosen_option') ?></th>
            <th><?= t('user.selections.chosen_price') ?></th>
            <th><?= t('user.selections.variance') ?></th>
            <th><?= t('user.selections.due_date') ?></th>
            <th><?= t('common.status') ?></th>
            <th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $sel): ?>
          <tr>
            <td><?= e(local($sel, 'name')) ?></td>
            <td><?= money((float)$sel['allowance_amount']) ?></td>
            <td><?= $sel['chosen_option'] ? e(local($sel['chosen_option'], 'name')) : '—' ?></td>
            <td><?= $sel['chosen_option'] ? money((float)$sel['chosen_option']['price']) : '—' ?></td>
            <td style="color:<?= $sel['variance'] === null ? 'inherit' : ($sel['variance'] > 0 ? 'var(--danger)' : 'var(--success)') ?>;font-weight:600;">
              <?= $sel['variance'] === null ? '—' : ($sel['variance'] > 0 ? '+' : '') . number_format($sel['variance'], 2) ?>
            </td>
            <td><?= e($sel['due_date'] ?: '—') ?></td>
            <td>
              <form method="post" action="/app/selections/<?= $sel['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?>
                <select name="status" onchange="this.form.submit()" class="badge badge-<?= ['pending'=>'gray','client_selected'=>'blue','approved'=>'yellow','ordered'=>'blue','installed'=>'green'][$sel['status']] ?? 'gray' ?>" style="border:none;padding:4px 8px;">
                  <?php foreach ($statuses as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $sel['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td style="white-space:nowrap;">
              <a href="/app/selections/<?= $sel['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
              <form method="post" action="/app/selections/<?= $sel['id'] ?>/delete" style="display:inline;" onsubmit="return confirm('<?= t('user.selections.remove_confirm') ?>');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light"><?= t('common.delete') ?></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
@endsection
