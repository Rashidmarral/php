@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.estimates.new_title') ?></h1>
  <a href="/app/estimates" class="btn btn-light"><?= t('user.estimates.back_to_estimates') ?></a>
</div>

<form method="post" action="/app/estimates" class="card" style="max-width:900px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.estimates.title_en') ?></label><input type="text" name="title" required placeholder="e.g. Villa Renovation Estimate"></div>
    <div class="form-group"><label><?= t('user.estimates.title_ar') ?></label><input type="text" name="title_ar" dir="rtl" placeholder="عنوان التسعيرة بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id">
        <option value=""><?= t('user.projects.no_client') ?></option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <label><?= t('user.estimates.link_project_optional') ?></label>
    <select name="project_id">
      <option value=""><?= t('user.invoices.no_project') ?></option>
      <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>

  <label><?= t('user.invoices.line_items') ?></label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:32%"><?= t('common.description_en') ?></th><th style="width:28%"><?= t('common.description_ar') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_cost') ?> (SAR)</th><th><?= t('user.invoices.line_total') ?></th><th></th></tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_description[]" placeholder="e.g. Demolition & site prep"></td>
        <td><input type="text" name="item_description_ar[]" dir="rtl" placeholder="الوصف بالعربية"></td>
        <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
        <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  <div style="display:flex;gap:8px;">
    <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.invoices.add_line_item') ?></button>
    <button type="button" id="open-library-picker" class="btn btn-sm btn-outline"><?= t('user.invoices.pull_from_library') ?></button>
  </div>
  <div class="total-row"><?= t('common.total') ?>: <span id="grand-total">0.00</span> SAR</div>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;"><?= t('user.estimates.create_estimate') ?></button>
</form>

@include('app.partials.library-picker')

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="item_description[]" placeholder="Description"></td>
      <td><input type="text" name="item_description_ar[]" dir="rtl" placeholder="الوصف بالعربية"></td>
      <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
      <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function recalc() {
    let total = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      total += lineTotal;
    });
    grandTotal.textContent = total.toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
      recalc();
    }
  });

  document.addEventListener('library-item-picked', (e) => {
    const rows = body.querySelectorAll('tr');
    const firstRow = rows[0];
    const firstEmpty = firstRow && !firstRow.querySelector('[name="item_description[]"]').value;
    const tr = firstEmpty ? firstRow : rowTemplate();
    if (!firstEmpty) body.appendChild(tr);
    tr.querySelector('[name="item_description[]"]').value = e.detail.description;
    tr.querySelector('[name="item_description_ar[]"]').value = e.detail.descriptionAr || '';
    tr.querySelector('.cost').value = e.detail.unitCost;
    recalc();
  });

  recalc();
})();
</script>

@endsection
