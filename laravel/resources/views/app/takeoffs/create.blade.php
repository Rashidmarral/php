@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.takeoffs.new_title') ?></h1>
  <a href="/app/takeoffs" class="btn btn-light"><?= t('user.takeoffs.back_to_takeoffs') ?></a>
</div>

<form method="post" action="/app/takeoffs" enctype="multipart/form-data" class="card" style="max-width:600px;">
  <?= csrf_field() ?>
  <div class="form-group">
    <label><?= t('user.takeoffs.name_label') ?></label>
    <input type="text" name="name" required maxlength="150" placeholder="e.g. Villa Ground Floor Plan">
  </div>
  <div class="form-group">
    <label><?= t('user.takeoffs.project_optional') ?></label>
    <select name="project_id" class="combo-select" data-placeholder="<?= e(t('common.search_projects_placeholder')) ?>" data-no-results="<?= e(t('common.no_projects_found')) ?>">
      <option value=""><?= t('user.takeoffs.no_project') ?></option>
      <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label><?= t('user.takeoffs.plan_image') ?></label>
    <input type="file" name="plan_image" accept="image/png,image/jpeg,image/webp,application/pdf">
    <p class="help-text"><?= t('user.takeoffs.plan_image_hint') ?></p>
    <p class="help-text"><?= t('user.takeoffs.plan_file_hint') ?></p>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('user.takeoffs.create_and_open') ?></button>
</form>

@endsection
