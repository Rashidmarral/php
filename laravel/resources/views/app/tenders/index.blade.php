@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.tenders.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.tenders.hint') ?></p>

<div class="toolbar" style="margin-bottom:20px;">
  <a href="/app/tenders" class="btn btn-sm <?= $categoryFilter === '' ? 'btn-primary' : 'btn-light' ?>"><?= t('user.leads.all_col') ?></a>
  <?php foreach ($categories as $key => $label): ?>
    <a href="/app/tenders?category=<?= $key ?>" class="btn btn-sm <?= $categoryFilter === $key ? 'btn-primary' : 'btn-light' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($tenders)): ?>
  <div class="empty-state card">
    <div class="icon">📋</div>
    <p><?= t('user.tenders.none_yet') ?></p>
  </div>
<?php else: ?>
  <div class="grid grid-3">
    <?php foreach ($tenders as $tItem):
      $daysLeft = $tItem['submission_deadline'] ? (int) ceil((strtotime($tItem['submission_deadline']) - strtotime(date('Y-m-d'))) / 86400) : null;
      if ($daysLeft === null) { $badge = 'gray'; $label = '—'; }
      elseif ($daysLeft < 0) { $badge = 'red'; $label = t('user.tenders.closed'); }
      elseif ($daysLeft <= 7) { $badge = 'red'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
      elseif ($daysLeft <= 21) { $badge = 'yellow'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
      else { $badge = 'green'; $label = t('user.tenders.days_left', ['days' => $daysLeft]); }
    ?>
      <a href="/app/tenders/<?= $tItem['id'] ?>" class="card feature-card" style="text-decoration:none;color:inherit;">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
          <span class="badge badge-blue"><?= e($categories[$tItem['category']] ?? $tItem['category']) ?></span>
          <span class="badge badge-<?= $badge ?>"><?= e($label) ?></span>
        </div>
        <h3 style="margin-top:10px;"><?= e(local($tItem, 'title_en', 'title_ar')) ?></h3>
        <p class="help-text" style="margin:2px 0 8px;"><?= e(local($tItem, 'entity_name_en', 'entity_name_ar')) ?><?php if ($tItem['location_city']): ?> · <?= e($tItem['location_city']) ?><?php endif; ?></p>
        <?php $desc = (string) local($tItem, 'description_en', 'description_ar'); $descShort = mb_strlen($desc) > 110 ? mb_substr($desc, 0, 110) . '…' : $desc; ?>
        <p><?= e($descShort) ?></p>
        <p style="margin-top:10px;font-size:13px;">
          <?= t('user.tenders.deadline_label') ?> <strong><?= e($tItem['submission_deadline'] ?: '—') ?></strong>
          <?php if ($tItem['estimated_value_sar'] !== null): ?><br><?= t('common.value') ?>: <?= money((float) $tItem['estimated_value_sar']) ?><?php endif; ?>
        </p>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

@endsection
