@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= $rfq ? t('user.rfqs.edit_title') : t('user.rfqs.new_title') ?></h1>
  <a href="<?= $rfq ? '/app/rfqs/' . $rfq['id'] : '/app/rfqs' ?>" class="btn btn-light"><?= t('user.rfqs.back_to_list') ?></a>
</div>

<form method="post" action="<?= $rfq ? '/app/rfqs/' . $rfq['id'] : '/app/rfqs' ?>" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group" style="flex:1;">
      <label><?= t('common.title') ?></label>
      <input type="text" name="title" required value="<?= e($rfq['title'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label><?= t('user.rfqs.project_optional') ?></label>
      <select name="project_id">
        <option value=""><?= t('user.rfqs.standalone_project') ?></option>
        <?php foreach ($projects as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($rfq['project_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= e(local($p, 'name')) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('user.rfqs.due_date') ?></label>
      <input type="date" name="due_date" value="<?= e($rfq['due_date'] ?? '') ?>">
    </div>
  </div>

  <?php if ($rfq): ?>
  <div class="form-group">
    <label><?= t('common.status') ?></label>
    <select name="status">
      <?php foreach (\App\Models\Rfq::STATUSES as $key => $label): ?>
        <?php if ($key === 'awarded') continue; ?>
        <option value="<?= $key ?>" <?= $rfq['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>

  <div class="form-group">
    <label><?= t('common.notes') ?></label>
    <input type="text" name="notes" value="<?= e($rfq['notes'] ?? '') ?>">
  </div>

  <?php if (!$rfq): ?>
    <label><?= t('user.rfqs.items_title') ?></label>
    <table class="line-items" id="items-table">
      <thead><tr><th style="width:55%"><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit') ?></th><th></th></tr></thead>
      <tbody id="items-body">
        <tr>
          <td><input type="text" name="item_description[]" placeholder="e.g. 50 bags of cement"></td>
          <td><input type="number" step="0.01" name="item_qty[]" value="1"></td>
          <td><input type="text" name="item_unit[]" placeholder="e.g. bag"></td>
          <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
        </tr>
      </tbody>
    </table>
    <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.rfqs.add_item_btn') ?></button>
  <?php endif; ?>

  <div style="margin-top:16px;">
    <button type="submit" class="btn btn-primary"><?= t('common.save') ?></button>
  </div>
</form>

<?php if (!$rfq): ?>
<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="item_description[]" placeholder="Description"></td>
      <td><input type="number" step="0.01" name="item_qty[]" value="1"></td>
      <td><input type="text" name="item_unit[]" placeholder="Unit"></td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  addBtn.addEventListener('click', () => body.appendChild(rowTemplate()));
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row') && body.querySelectorAll('tr').length > 1) {
      e.target.closest('tr').remove();
    }
  });
})();
</script>
<?php endif; ?>
@endsection
