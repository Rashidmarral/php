@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.projects.duplicate_title') ?></h1>
  <a href="/app/projects/<?= $project['id'] ?>" class="btn btn-light"><?= t('user.projects.back_to_project') ?></a>
</div>

<form method="post" action="/app/projects/<?= $project['id'] ?>/duplicate" class="card" style="max-width:680px;">
  <?= csrf_field() ?>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.duplicate_hint') ?></p>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.projects.name_en') ?></label><input type="text" name="name" required value="<?= e(local($project, 'name')) ?> (Copy)"></div>
    <div class="form-group"><label><?= t('user.projects.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= $project['name_ar'] ? e($project['name_ar']) . ' (نسخة)' : '' ?>" placeholder="اسم المشروع بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id">
        <option value=""><?= t('user.projects.no_client') ?></option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($project['client_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.projects.new_start_date') ?></label><input type="date" name="start_date" value="<?= date('Y-m-d') ?>"></div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('user.projects.duplicate_project') ?></button>
</form>

@endsection
