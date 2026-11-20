@extends('layouts.admin')

@section('content')
<?php
use App\Support\SaudiCities;

$isEdit = $tender !== null;
?>
<div class="page-head">
  <h1><?= $isEdit ? t('admin.tenders.edit') : t('admin.tenders.new_title') ?></h1>
  <a href="/admin/tenders" class="btn btn-light">← <?= t('admin.tenders.back') ?></a>
</div>

<form method="post" action="<?= $isEdit ? '/admin/tenders/' . $tender['id'] : '/admin/tenders' ?>">
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom:20px;">
    <h3><?= t('admin.tenders.details') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('common.category') ?></label>
        <select name="category">
          <?php foreach ($categories as $key => $label): ?>
            <option value="<?= $key ?>" <?= ($tender['category'] ?? 'government') === $key ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('admin.tenders.deadline') ?></label><input type="date" name="submission_deadline" value="<?= e($tender['submission_deadline'] ?? '') ?>" required></div>
      <div class="form-group"><label><?= t('admin.tenders.est_value') ?></label><input type="number" step="0.01" min="0" max="999999999999.99" inputmode="decimal" name="estimated_value_sar" value="<?= e($tender['estimated_value_sar'] ?? '') ?>" placeholder="<?= t('admin.tenders.est_value_placeholder') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.tenders.entity_en') ?></label><input type="text" name="entity_name_en" maxlength="150" value="<?= e($tender['entity_name_en'] ?? '') ?>" placeholder="e.g. Ministry of Municipal and Rural Affairs" required></div>
      <div class="form-group"><label><?= t('admin.tenders.entity_ar') ?></label><input type="text" name="entity_name_ar" maxlength="150" value="<?= e($tender['entity_name_ar'] ?? '') ?>" dir="rtl"></div>
      <div class="form-group">
        <label><?= t('common.city') ?></label>
        <select name="location_city" class="city-select" data-placeholder="<?= e(t('user.settings.city_search_placeholder')) ?>" data-no-results="<?= e(t('user.settings.city_no_results')) ?>">
          <option value=""><?= t('common.select') ?></option>
          <?php foreach (SaudiCities::withExtra($tender['location_city'] ?? null) as $city): ?>
            <option value="<?= e($city['en']) ?>" data-ar="<?= e($city['ar']) ?>" <?= ($tender['location_city'] ?? '') === $city['en'] ? 'selected' : '' ?>><?= e($city['ar'] !== '' ? $city['en'] . ' / ' . $city['ar'] : $city['en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.link') ?> (<?= t('admin.tenders.source_url_hint') ?>)</label><input type="url" name="source_url" maxlength="255" value="<?= e($tender['source_url'] ?? '') ?>" placeholder="https://tenders.etimad.sa"></div>
      <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" step="1" min="0" max="99999" inputmode="numeric" name="sort_order" value="<?= e((string) ($tender['sort_order'] ?? 0)) ?>"></div>
      <div class="form-group"><label><input type="checkbox" name="is_active" value="1" <?= ($tender === null || !empty($tender['is_active'])) ? 'checked' : '' ?>> <?= t('common.active') ?></label></div>
    </div>
  </div>

  <div class="grid grid-2" style="gap:20px;margin-bottom:20px;">
    <div class="card">
      <h3><?= t('admin.tenders.english_content') ?></h3>
      <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title_en" maxlength="255" value="<?= e($tender['title_en'] ?? '') ?>" required></div>
      <div class="form-group"><label><?= t('common.description') ?></label><textarea name="description_en" rows="6"><?= e($tender['description_en'] ?? '') ?></textarea></div>
    </div>
    <div class="card">
      <h3><?= t('admin.tenders.arabic_content') ?></h3>
      <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title_ar" maxlength="255" value="<?= e($tender['title_ar'] ?? '') ?>" dir="rtl"></div>
      <div class="form-group"><label><?= t('common.description') ?></label><textarea name="description_ar" rows="6" dir="rtl"><?= e($tender['description_ar'] ?? '') ?></textarea></div>
    </div>
  </div>

  <button type="submit" class="btn btn-primary"><?= $isEdit ? t('common.save_changes') : t('admin.tenders.create') ?></button>
</form>

@endsection
