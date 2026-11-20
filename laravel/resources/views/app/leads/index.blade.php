@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.leads.title') ?></h1>
  <a href="/app/leads/create" class="btn btn-primary"><?= t('user.leads.new') ?></a>
</div>

<div class="toolbar" style="margin-bottom:20px;">
  <a href="/app/leads" class="btn btn-sm <?= $statusFilter === '' ? 'btn-primary' : 'btn-light' ?>"><?= t('user.leads.all_col') ?> (<?= array_sum($counts) ?>)</a>
  <?php foreach ($statuses as $s): ?>
    <a href="/app/leads?status=<?= $s ?>" class="btn btn-sm <?= $statusFilter === $s ? 'btn-primary' : 'btn-light' ?>"><?= ucfirst($s) ?> (<?= $counts[$s] ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (empty($leads)): ?>
  <div class="empty-state card">
    <div class="icon">🎯</div>
    <p><?= t('user.leads.none_yet') ?> <a href="/app/leads/create"><?= t('user.leads.add_first') ?></a>.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.contact') ?></th><th><?= t('user.leads.source_col') ?></th><th><?= t('user.leads.est_value_col') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($leads as $l): ?>
      <tr>
        <td><a href="/app/leads/<?= $l['id'] ?>/edit"><?= e($l['name']) ?></a><?php if ($l['company_name']): ?><br><span class="help-text"><?= e(local($l, 'company_name')) ?></span><?php endif; ?></td>
        <td><?= e($l['email'] ?: '—') ?><?php if ($l['phone']): ?><br><span class="help-text"><bdi><?= e($l['phone']) ?></bdi></span><?php endif; ?></td>
        <td><span class="badge badge-gray"><?= e(ucwords(str_replace('_', ' ', $l['source']))) ?></span></td>
        <td><?= money((float)$l['estimated_value']) ?></td>
        <td>
          <form method="post" action="/app/leads/<?= $l['id'] ?>/status" style="display:inline;">
            <?= csrf_field() ?>
            <select name="status" onchange="this.form.requestSubmit()" style="width:auto;display:inline-block;padding:4px 8px;font-size:12.5px;">
              <?php foreach ($statuses as $s): ?>
                <option value="<?= $s ?>" <?= $l['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
        <td style="display:flex;gap:6px;">
          <?php if (empty($l['converted_client_id']) && $l['status'] !== 'lost'): ?>
            <form method="post" action="/app/leads/<?= $l['id'] ?>/convert" onsubmit="return confirm('<?= t('user.leads.convert_confirm') ?>');" style="display:inline;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.leads.to_client') ?></button>
            </form>
          <?php elseif ($l['converted_client_id']): ?>
            <a href="/app/clients/<?= $l['converted_client_id'] ?>/edit" class="btn btn-sm btn-light"><?= t('user.leads.view_client') ?></a>
          <?php endif; ?>
          <form method="post" action="/app/leads/<?= $l['id'] ?>/delete" onsubmit="return confirm('<?= t('user.leads.delete_confirm') ?>');" style="display:inline;">
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
