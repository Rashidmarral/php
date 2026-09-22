@extends('layouts.app')

@section('content')
<?php
  $daysLeft = $tender['submission_deadline'] ? (int) ceil((strtotime($tender['submission_deadline']) - strtotime(date('Y-m-d'))) / 86400) : null;
  if ($daysLeft === null) { $badge = 'gray'; $label = '—'; }
  elseif ($daysLeft < 0) { $badge = 'red'; $label = t('user.tenders.closed'); }
  elseif ($daysLeft <= 7) { $badge = 'red'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
  elseif ($daysLeft <= 21) { $badge = 'yellow'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
  else { $badge = 'green'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
?>
<div class="page-head">
  <div>
    <a href="/app/tenders" class="help-text">← <?= t('user.tenders.back_to_tenders') ?></a>
    <h1 style="margin-top:6px;"><?= e(local($tender, 'title_en', 'title_ar')) ?></h1>
    <p class="help-text" style="margin-top:2px;"><?= e(local($tender, 'entity_name_en', 'entity_name_ar')) ?><?php if ($tender['location_city']): ?> · <?= e($tender['location_city']) ?><?php endif; ?></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">
  <div class="card">
    <div style="display:flex;gap:8px;margin-bottom:14px;">
      <span class="badge badge-blue"><?= e($categories[$tender['category']] ?? $tender['category']) ?></span>
      <span class="badge badge-<?= $badge ?>"><?= e($label) ?></span>
    </div>
    <h3 style="font-size:14px;"><?= t('common.description') ?></h3>
    <p><?= nl2br(e(local($tender, 'description_en', 'description_ar'))) ?: '<span class="help-text">' . t('user.tenders.no_description') . '</span>' ?></p>
  </div>

  <div class="card" style="position:sticky;top:90px;">
    <h3 style="font-size:14px;"><?= t('user.tenders.opportunity_details') ?></h3>
    <div class="form-group">
      <label><?= t('user.tenders.deadline_label') ?></label>
      <p><strong><?= e($tender['submission_deadline'] ?: '—') ?></strong></p>
    </div>
    <div class="form-group">
      <label><?= t('common.value') ?></label>
      <p><?= $tender['estimated_value_sar'] !== null ? money((float) $tender['estimated_value_sar']) : t('user.tenders.value_not_disclosed') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('admin.tenders.entity_col') ?></label>
      <p><?= e(local($tender, 'entity_name_en', 'entity_name_ar')) ?></p>
    </div>
    <?php if ($tender['location_city']): ?>
    <div class="form-group">
      <label><?= t('common.city') ?></label>
      <p><?= e($tender['location_city']) ?></p>
    </div>
    <?php endif; ?>
    <?php if ($tender['source_url']): ?>
      <a href="<?= e($tender['source_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary" style="width:100%;text-align:center;display:block;"><?= t('user.tenders.view_official_listing') ?> ↗</a>
    <?php else: ?>
      <p class="help-text"><?= t('user.tenders.no_source_url') ?></p>
    <?php endif; ?>
  </div>
</div>

@endsection
