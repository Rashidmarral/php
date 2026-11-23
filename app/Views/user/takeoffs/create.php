<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('user.takeoffs.new_title') ?></h1>
  <a href="/app/takeoffs" class="btn btn-light"><?= t('user.takeoffs.back_to_takeoffs') ?></a>
</div>

<form method="post" action="/app/takeoffs" enctype="multipart/form-data" class="card" style="max-width:600px;">
  <?= Csrf::field() ?>
  <div class="form-group">
    <label><?= t('user.takeoffs.name_label') ?></label>
    <input type="text" name="name" required placeholder="e.g. Villa Ground Floor Plan">
  </div>
  <div class="form-group">
    <label><?= t('user.takeoffs.project_optional') ?></label>
    <select name="project_id">
      <option value=""><?= t('user.takeoffs.no_project') ?></option>
      <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label><?= t('user.takeoffs.plan_image') ?></label>
    <input type="file" name="plan_image" accept="image/png,image/jpeg,image/webp">
    <p class="help-text"><?= t('user.takeoffs.plan_image_hint') ?></p>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('user.takeoffs.create_and_open') ?></button>
</form>
