@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/subcontracts">&larr; <?= t('user.subcontracts.title') ?></a></p>
    <h1><?= t('user.subcontracts.create_title') ?></h1>
  </div>
</div>

<form method="post" action="/app/projects/<?= $project['id'] ?>/subcontracts" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.subcontracts.subcontractor') ?></label>
      <select name="supplier_id" required>
        <option value="">—</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" required></div>
  </div>
  <div class="form-group"><label><?= t('user.subcontracts.description') ?></label><textarea name="description" rows="3"></textarea></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.subcontracts.contract_value') ?></label><input type="number" step="0.01" min="0.01" name="contract_value" value="0" required></div>
    <div class="form-group"><label><?= t('user.subcontracts.retention_percent') ?></label><input type="number" step="0.01" min="0" max="100" name="retention_percent" value="<?= e((string)$defaultRetentionPercent) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.subcontracts.start_date') ?></label><input type="date" name="start_date"></div>
    <div class="form-group"><label><?= t('user.subcontracts.end_date') ?></label><input type="date" name="end_date"></div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.save') ?></button>
</form>

@endsection
