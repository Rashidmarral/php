<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $project ? t('user.projects.edit_title') : t('user.projects.new_title') ?></h1>
  <a href="/app/projects" class="btn btn-light"><?= t('user.projects.back_to_projects') ?></a>
</div>

<form method="post" action="<?= $project ? '/app/projects/' . $project['id'] : '/app/projects' ?>" class="card" style="max-width:680px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.projects.name_en') ?></label><input type="text" name="name" required value="<?= View::e($project['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.projects.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($project['name_ar'] ?? '') ?>" placeholder="اسم المشروع بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id">
        <option value=""><?= t('user.projects.no_client') ?></option>
        <?php foreach ($clients as $c): ?>
          <option value="<?= $c['id'] ?>" <?= (($project['client_id'] ?? null) == $c['id']) ? 'selected' : '' ?>><?= View::e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('common.status') ?></label>
      <?php $status = $project['status'] ?? 'planning'; ?>
      <select name="status">
        <?php foreach (['planning' => t('user.projects.status_planning'), 'in_progress' => t('user.projects.status_in_progress'), 'on_hold' => t('user.projects.status_on_hold'), 'completed' => t('user.projects.status_completed')] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $status === $val ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.projects.budget_sar') ?></label><input type="number" step="0.01" name="budget" value="<?= View::e((string)($project['budget'] ?? '0')) ?>"></div>
    <div class="form-group"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.projects.start_date') ?></label><input type="date" name="start_date" value="<?= View::e($project['start_date'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.projects.end_date') ?></label><input type="date" name="end_date" value="<?= View::e($project['end_date'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.description_en') ?></label>
      <textarea name="description"><?= View::e($project['description'] ?? '') ?></textarea>
    </div>
    <div class="form-group">
      <label><?= t('common.description_ar') ?></label>
      <textarea name="description_ar" dir="rtl" placeholder="الوصف بالعربية"><?= View::e($project['description_ar'] ?? '') ?></textarea>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= $project ? t('common.save_changes') : t('user.projects.create_project') ?></button>
</form>
