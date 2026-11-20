@extends('layouts.app')

{{--
  Shared create/edit form for a Selection: its own category/name/allowance/due_date
  fields, then a nested options table with client-side add-row/remove-row JS before a
  single submit — same array-field shape (option_name[], option_price[], ...) that
  SelectionController::optionsFromInput() parses, mirroring
  resources/views/app/assemblies/form.blade.php's own items editor. Each option row can
  optionally carry an uploaded image; option_existing_image[]/option_existing_chosen[]
  carry a row's current image_path/is_chosen forward when the row isn't touched.
--}}
@php
  $rows = $options ?: [[
      'name' => '',
      'name_ar' => '',
      'description' => '',
      'price' => 0,
      'image_path' => null,
      'is_chosen' => false,
  ]];
@endphp

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/selections">&larr; <?= t('user.selections.title') ?></a></p>
    <h1><?= $selection ? t('user.selections.edit_title') : t('user.selections.new_title') ?></h1>
  </div>
</div>

<form method="post" action="<?= $selection ? '/app/selections/' . $selection['id'] : '/app/projects/' . $project['id'] . '/selections' ?>" enctype="multipart/form-data" class="card">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.category') ?></label><input type="text" name="category" required maxlength="100" value="<?= e($selection['category'] ?? '') ?>" placeholder="e.g. Flooring, Cabinets, Fixtures, Paint"></div>
    <div class="form-group"><label><?= t('user.selections.allowance') ?> (SAR)</label><input type="number" inputmode="decimal" step="0.01" min="0" name="allowance_amount" value="<?= e($selection['allowance_amount'] ?? 0) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required maxlength="150" value="<?= e($selection['name'] ?? '') ?>" placeholder="e.g. Kitchen Countertop"></div>
    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" maxlength="150" value="<?= e($selection['name_ar'] ?? '') ?>" placeholder="الاسم بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.description_en') ?></label><input type="text" name="description" maxlength="2000" value="<?= e($selection['description'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl" maxlength="2000" value="<?= e($selection['description_ar'] ?? '') ?>"></div>
  </div>
  <div class="form-group" style="max-width:220px;"><label><?= t('user.selections.due_date') ?></label><input type="date" name="due_date" value="<?= e($selection['due_date'] ?? '') ?>"></div>

  <label><?= t('user.selections.options_label') ?></label>
  <p class="help-text" style="margin-top:-4px;"><?= t('user.selections.options_hint') ?></p>
  <div style="overflow-x:auto;">
  <table class="line-items" id="selection-options-table">
    <thead><tr>
      <th style="width:18%"><?= t('common.name_en') ?></th>
      <th style="width:16%"><?= t('common.name_ar') ?></th>
      <th style="width:22%"><?= t('common.description_en') ?></th>
      <th style="width:12%"><?= t('common.unit_cost') ?> (SAR)</th>
      <th style="width:16%"><?= t('user.selections.option_image') ?></th>
      <th></th>
    </tr></thead>
    <tbody id="selection-options-body">
      <?php foreach ($rows as $row): ?>
      <tr>
        <td><input type="text" name="option_name[]" maxlength="150" value="<?= e($row['name'] ?? '') ?>" placeholder="e.g. Quartz"></td>
        <td><input type="text" name="option_name_ar[]" dir="rtl" maxlength="150" value="<?= e($row['name_ar'] ?? '') ?>" placeholder="الاسم بالعربية"></td>
        <td><input type="text" name="option_description[]" maxlength="2000" value="<?= e($row['description'] ?? '') ?>"></td>
        <td><input type="number" inputmode="decimal" step="0.01" min="0" name="option_price[]" value="<?= e($row['price'] ?? 0) ?>" class="cost"></td>
        <td>
          <?php if (!empty($row['image_path'])): ?>
            <img src="<?= e($row['image_path']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:4px;vertical-align:middle;margin-inline-end:6px;">
          <?php endif; ?>
          <input type="file" name="option_image[]" accept="image/jpeg,image/png,image/webp" style="width:130px;">
          <input type="hidden" name="option_existing_image[]" value="<?= e($row['image_path'] ?? '') ?>">
          <input type="hidden" name="option_existing_chosen[]" value="<?= !empty($row['is_chosen']) ? '1' : '0' ?>">
          <?php if (!empty($row['is_chosen'])): ?><br><span class="badge badge-green" style="margin-top:4px;"><?= t('user.selections.client_picked') ?></span><?php endif; ?>
        </td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <div style="margin-top:8px;">
    <button type="button" id="selection-add-row" class="btn btn-sm btn-outline"><?= t('user.selections.add_option') ?></button>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:20px;"><?= $selection ? t('common.save_changes') : t('user.selections.add_selection') ?></button>
</form>

<script>
(function() {
  const body = document.getElementById('selection-options-body');
  const addBtn = document.getElementById('selection-add-row');

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="option_name[]" maxlength="150" placeholder="Name"></td>
      <td><input type="text" name="option_name_ar[]" dir="rtl" maxlength="150" placeholder="الاسم بالعربية"></td>
      <td><input type="text" name="option_description[]" maxlength="2000" placeholder="Description"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="option_price[]" value="0" class="cost"></td>
      <td>
        <input type="file" name="option_image[]" accept="image/jpeg,image/png,image/webp" style="width:130px;">
        <input type="hidden" name="option_existing_image[]" value="">
        <input type="hidden" name="option_existing_chosen[]" value="0">
      </td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); });
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
    }
  });
})();
</script>
@endsection
