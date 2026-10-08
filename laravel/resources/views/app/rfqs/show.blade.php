@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/rfqs">&larr; <?= t('user.rfqs.title') ?></a></p>
    <h1><?= e($rfq['title']) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?= $project ? e(local($project, 'name')) : t('user.rfqs.no_project') ?>
      <?php if ($rfq['due_date']): ?> · <?= t('user.rfqs.due_date') ?>: <?= e($rfq['due_date']) ?><?php endif; ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php $statusBadge = ['draft' => 'gray', 'sent' => 'blue', 'comparing' => 'yellow', 'awarded' => 'green', 'cancelled' => 'red'][$rfq['status']] ?? 'gray'; ?>
    <span class="badge badge-<?= $statusBadge ?>" style="font-size:13px;padding:6px 14px;"><?= e($statuses[$rfq['status']] ?? ucfirst($rfq['status'])) ?></span>
    <?php if ($isEditable && auth()->user()->can('write')): ?>
      <a href="/app/rfqs/<?= $rfq['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <?php endif; ?>
    <?php if ($rfq['status'] !== 'awarded' && auth()->user()->can('write')): ?>
      <form method="post" action="/app/rfqs/<?= $rfq['id'] ?>/delete" onsubmit="return confirm('<?= t('user.rfqs.remove_confirm') ?>');">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($rfq['notes'])): ?>
  <div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:14px;"><?= t('common.notes') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($rfq['notes']) ?></p>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.rfqs.items_title') ?></h3>

  <?php if ($isEditable && auth()->user()->can('write')): ?>
  <form method="post" action="/app/rfqs/<?= $rfq['id'] ?>/items" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:2;min-width:220px;">
      <label><?= t('common.description') ?></label>
      <input type="text" name="description" required>
    </div>
    <div class="form-group" style="margin:0;width:100px;">
      <label><?= t('common.qty') ?></label>
      <input type="number" step="0.01" name="qty" value="1">
    </div>
    <div class="form-group" style="margin:0;width:120px;">
      <label><?= t('common.unit') ?></label>
      <input type="text" name="unit">
    </div>
    <button type="submit" class="btn btn-sm btn-primary"><?= t('user.rfqs.add_item_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <p class="help-text"><?= t('user.rfqs.item_description_required') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><?= e($i['description']) ?></td>
          <td><?= e((string) $i['qty']) ?></td>
          <td><?= e($i['unit'] ?: '—') ?></td>
          <td>
            <?php if ($isEditable && auth()->user()->can('write')): ?>
              <form method="post" action="/app/rfqs/<?= $rfq['id'] ?>/items/<?= $i['id'] ?>/delete" onsubmit="return confirm('<?= t('common.delete') ?>?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h3 style="font-size:14px;"><?= t('user.rfqs.quotes_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.rfqs.quotes_hint') ?></p>

  <?php if ($isEditable && auth()->user()->can('write')): ?>
  <form method="post" action="/app/rfqs/<?= $rfq['id'] ?>/quotes" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('common.supplier') ?></label>
        <select name="supplier_id" required>
          <option value=""></option>
          <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('user.rfqs.total_amount') ?> (SAR)</label><input type="number" step="0.01" name="total_amount" required></div>
      <div class="form-group"><label><?= t('user.rfqs.lead_time_days') ?></label><input type="number" step="1" name="lead_time_days"></div>
      <div class="form-group"><label><?= t('common.date') ?></label><input type="date" name="submitted_at" value="<?= date('Y-m-d') ?>"></div>
    </div>
    <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('user.rfqs.record_quote_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($quotes)): ?>
    <p class="help-text"><?= t('user.rfqs.no_quotes_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.supplier') ?></th><th><?= t('user.rfqs.total_amount') ?></th><th><?= t('user.rfqs.lead_time_days') ?></th><th><?= t('common.date') ?></th><th><?= t('common.notes') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($quotes as $q): ?>
        <tr<?= $q['is_awarded'] ? ' style="background:#eefbf0;"' : '' ?>>
          <td>
            <?= e($q['supplier_name']) ?>
            <?php if ($q['is_awarded']): ?> <span class="badge badge-green"><?= t('user.rfqs.awarded_badge') ?></span><?php endif; ?>
          </td>
          <td>
            <?= money((float) $q['total_amount']) ?>
            <?php if ($q['is_cheapest']): ?> <span class="badge badge-blue"><?= t('user.rfqs.cheapest_badge') ?></span><?php endif; ?>
          </td>
          <td>
            <?= $q['lead_time_days'] !== null ? e((string) $q['lead_time_days']) : '—' ?>
            <?php if ($q['is_fastest']): ?> <span class="badge badge-blue"><?= t('user.rfqs.fastest_badge') ?></span><?php endif; ?>
          </td>
          <td><?= e($q['submitted_at'] ?: '—') ?></td>
          <td><?= e($q['notes'] ?: '—') ?></td>
          <td>
            <?php if ($rfq['status'] !== 'awarded' && auth()->user()->can('write')): ?>
              <form method="post" action="/app/rfqs/<?= $rfq['id'] ?>/quotes/<?= $q['id'] ?>/award" onsubmit="return confirm('<?= t('user.rfqs.award_confirm') ?>');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-primary"><?= t('user.rfqs.award_btn') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
