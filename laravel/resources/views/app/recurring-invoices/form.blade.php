@extends('layouts.app')

@php $isEdit = !empty($template); @endphp

@section('content')
<div class="page-head">
  <h1><?= $isEdit ? t('user.recurring_invoices.edit_title') : t('user.recurring_invoices.new_title') ?></h1>
  <a href="/app/recurring-invoices" class="btn btn-light"><?= t('user.recurring_invoices.back_to_list') ?></a>
</div>

<form method="post" action="<?= $isEdit ? '/app/recurring-invoices/' . $template['id'] : '/app/recurring-invoices' ?>" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" value="<?= e($template['title'] ?? '') ?>" placeholder="e.g. Monthly Site Security Retainer"></div>

  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id">
        <option value=""><?= t('user.projects.no_client') ?></option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" <?= ($template['client_id'] ?? null) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('common.project') ?></label>
      <select name="project_id">
        <option value=""><?= t('user.invoices.no_project') ?></option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>" <?= ($template['project_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.recurring_invoices.frequency') ?></label>
      <select name="frequency">
        <?php foreach ($frequencies as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($template['frequency'] ?? 'monthly') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('user.recurring_invoices.next_run_date') ?></label>
      <input type="date" name="next_run_date" value="<?= e($template['next_run_date'] ?? now()->format('Y-m-d')) ?>">
      <p class="help-text"><?= t('user.recurring_invoices.next_run_date_hint') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('user.recurring_invoices.due_days') ?></label>
      <input type="number" min="0" name="due_days" value="<?= e((string) ($template['due_days'] ?? 14)) ?>">
      <p class="help-text"><?= t('user.recurring_invoices.due_days_hint') ?></p>
    </div>
  </div>

  <label><?= t('user.invoices.line_items') ?></label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:50%"><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?> (SAR)</th><th><?= t('user.invoices.line_total') ?></th><th></th></tr></thead>
    <tbody id="items-body">
      <?php if (!empty($items)): ?>
        <?php foreach ($items as $it): ?>
          <tr>
            <td><input type="text" name="item_description[]" value="<?= e($it['description']) ?>" placeholder="e.g. Monthly retainer fee"></td>
            <td><input type="number" step="0.01" name="item_qty[]" value="<?= e((string) $it['qty']) ?>" class="qty"></td>
            <td><input type="number" step="0.01" name="item_price[]" value="<?= e((string) $it['unit_price']) ?>" class="cost"></td>
            <td class="line-total">0.00</td>
            <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td><input type="text" name="item_description[]" placeholder="e.g. Monthly retainer fee"></td>
          <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
          <td><input type="number" step="0.01" name="item_price[]" value="0" class="cost"></td>
          <td class="line-total">0.00</td>
          <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
  <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.invoices.add_line_item') ?></button>

  <div class="form-row" style="margin-top:14px;align-items:end;">
    <div class="form-group" style="margin:0;">
      <label><input type="checkbox" name="apply_vat" id="apply-vat" value="1" <?= ($template['apply_vat'] ?? true) ? 'checked' : '' ?> style="width:auto;display:inline-block;"> <?= t('user.invoices.apply_vat') ?> (<?= e((string)$vatRate) ?>%)</label>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.invoices.retention_withheld_percent') ?></label>
      <input type="number" step="0.01" min="0" max="100" name="retention_percent" id="retention-percent" value="<?= e((string) ($template['retention_percent'] ?? $defaultRetentionPercent)) ?>">
    </div>
  </div>

  <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:10px;">
    <?= t('common.subtotal') ?>: <span id="grand-subtotal">0.00</span> SAR<br>
    <?= t('common.vat') ?>: <span id="grand-vat">0.00</span> SAR
  </div>
  <div class="total-row"><?= t('common.total') ?>: <span id="grand-total">0.00</span> SAR <?= t('user.recurring_invoices.per_period') ?></div>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;"><?= $isEdit ? t('common.update') : t('user.recurring_invoices.create') ?></button>
</form>

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');
  const grandSubtotal = document.getElementById('grand-subtotal');
  const grandVat = document.getElementById('grand-vat');
  const applyVat = document.getElementById('apply-vat');
  const vatRate = <?= json_encode($vatRate) ?>;

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="item_description[]" placeholder="Description"></td>
      <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
      <td><input type="number" step="0.01" name="item_price[]" value="0" class="cost"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function recalc() {
    let subtotal = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      subtotal += lineTotal;
    });
    const vat = applyVat.checked ? subtotal * vatRate / 100 : 0;
    grandSubtotal.textContent = subtotal.toFixed(2);
    grandVat.textContent = vat.toFixed(2);
    grandTotal.textContent = (subtotal + vat).toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  applyVat.addEventListener('change', recalc);
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
      recalc();
    }
  });

  recalc();
})();
</script>

@endsection
