@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $changeOrder['project_id'] ?>">&larr; <?= $project ? e(local($project, 'name')) : t('user.projects.title') ?></a></p>
    <h1><?= e(local($changeOrder, 'title')) ?></h1>
    <?php if (local($changeOrder, 'description') !== ''): ?>
      <p class="help-text" style="margin-top:4px;"><?= e(local($changeOrder, 'description')) ?></p>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <span class="badge badge-<?= $changeOrder['status']==='approved'?'green':($changeOrder['status']==='rejected'?'red':'yellow') ?>" style="font-size:13px;padding:6px 14px;"><?= e(ucfirst($changeOrder['status'])) ?></span>
    <?php if ($changeOrder['signed_at']): ?>
      <span class="badge badge-green" style="font-size:13px;padding:6px 14px;"><?= t('user.change_orders.client_signed_badge') ?></span>
    <?php else: ?>
      <span class="badge badge-gray" style="font-size:13px;padding:6px 14px;"><?= t('user.change_orders.awaiting_client_signature') ?></span>
    <?php endif; ?>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('common.amount') ?></div><div class="value"><?= money((float)$changeOrder['amount']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.change_orders.time_impact_days') ?></div><div class="value"><?= $changeOrder['time_impact_days'] !== null ? e((string)$changeOrder['time_impact_days']) . ' ' . t('common.days') : '—' ?></div></div>
  <div class="kpi"><div class="label"><?= t('common.status') ?></div><div class="value" style="font-size:16px;"><?= e(ucfirst($changeOrder['status'])) ?></div></div>
</div>

<div class="card" style="margin-top:24px;">
  <h3 style="font-size:14px;"><?= t('user.change_orders.time_impact_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.change_orders.time_impact_hint') ?></p>
  <?php if (auth()->user()->can('write')): ?>
  <form method="post" action="/app/change-orders/<?= $changeOrder['id'] ?>/time-impact" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;width:160px;">
      <label><?= t('user.change_orders.time_impact_days') ?></label>
      <input type="number" step="1" name="time_impact_days" value="<?= e((string)($changeOrder['time_impact_days'] ?? '')) ?>">
    </div>
    <button type="submit" class="btn btn-sm btn-outline"><?= t('common.save') ?></button>
  </form>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h3 style="font-size:14px;"><?= t('user.change_orders.items_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.change_orders.items_hint') ?></p>

  <?php if (auth()->user()->can('write')): ?>
  <form method="post" action="/app/change-orders/<?= $changeOrder['id'] ?>/items" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
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
    <div class="form-group" style="margin:0;width:140px;">
      <label><?= t('common.unit_price') ?> (SAR)</label>
      <input type="number" step="0.01" name="unit_price" value="0">
    </div>
    <button type="submit" class="btn btn-sm btn-primary"><?= t('user.change_orders.add_item_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($items)): ?>
    <p class="help-text"><?= t('user.change_orders.no_items_hint') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit') ?></th><th><?= t('common.unit_price') ?></th><th><?= t('user.invoices.line_total') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($items as $i): ?>
        <tr>
          <td><?= e($i['description']) ?></td>
          <td><?= e((string) $i['qty']) ?></td>
          <td><?= e($i['unit'] ?: '—') ?></td>
          <td><?= money((float)$i['unit_price']) ?></td>
          <td><?= money((float)$i['total']) ?></td>
          <td>
            <?php if (auth()->user()->can('write')): ?>
              <form method="post" action="/app/change-orders/<?= $changeOrder['id'] ?>/items/<?= $i['id'] ?>/delete" onsubmit="return confirm('<?= t('common.delete') ?>?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="help-text" style="margin-top:10px;"><?= t('user.change_orders.amount_auto_summed_hint') ?></p>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h3 style="font-size:14px;"><?= t('user.change_orders.client_signature_title') ?></h3>
  <?php if ($changeOrder['signed_at']): ?>
    <p class="help-text"><?= t('user.change_orders.signed_by_on', ['name' => $changeOrder['signed_by_name'], 'date' => $changeOrder['signed_at']]) ?></p>
    <?php if (!empty($changeOrder['signature_data'])): ?>
      <img src="<?= e($changeOrder['signature_data']) ?>" alt="Signature" style="max-width:320px;border:1px solid var(--border);border-radius:8px;margin-top:10px;background:#fff;">
    <?php endif; ?>
  <?php else: ?>
    <p class="help-text" style="margin-top:-8px;"><?= t('user.change_orders.send_for_signature_hint') ?></p>
    <div class="form-group">
      <label><?= t('user.change_orders.share_link_label') ?></label>
      <input type="text" readonly value="<?= e($shareUrl) ?>" onclick="this.select();" style="width:100%;max-width:520px;">
    </div>
  <?php endif; ?>
</div>
@endsection
