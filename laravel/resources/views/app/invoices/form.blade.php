@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.invoices.new_title') ?></h1>
  <a href="/app/invoices" class="btn btn-light"><?= t('user.invoices.back_to_invoices') ?></a>
</div>

<form method="post" action="/app/invoices" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.invoices.invoice_number') ?></label><input type="text" name="invoice_number" value="<?= e($nextNumber) ?>" maxlength="30"></div>
    <div class="form-group"><label><?= t('user.invoices.due_date') ?></label><input type="date" name="due_date"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.client') ?></label>
      <select name="client_id" class="combo-select" data-placeholder="<?= t('common.search_clients_placeholder') ?>" data-no-results="<?= t('common.no_clients_found') ?>">
        <option value=""><?= t('user.projects.no_client') ?></option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>" data-ar="<?= e($c['name_ar'] ?? '') ?>"><?= e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('common.project') ?></label>
      <select name="project_id" class="combo-select" data-placeholder="<?= t('common.search_projects_placeholder') ?>" data-no-results="<?= t('common.no_projects_found') ?>">
        <option value=""><?= t('user.invoices.no_project') ?></option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>" data-ar="<?= e($p['name_ar'] ?? '') ?>"><?= e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('pdf.payment_method') ?></label>
      <select name="payment_method">
        <option value=""><?= t('common.select') ?></option>
        <?php foreach ($paymentMethods as $method): ?>
          <option value="<?= e($method) ?>"><?= t('pdf.payment_method.'.$method) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('pdf.date_of_supply') ?></label>
      <input type="date" name="date_of_supply">
      <p class="help-text"><?= t('user.invoices.date_of_supply_hint') ?></p>
    </div>
  </div>

  <label><?= t('user.invoices.line_items') ?></label>
  <table class="line-items" id="items-table">
    <thead><tr>
      <th style="width:12%"><?= t('pdf.item_code') ?></th>
      <th style="width:32%"><?= t('common.description') ?></th>
      <th style="width:10%"><?= t('common.unit') ?></th>
      <th><?= t('common.qty') ?></th>
      <th><?= t('common.unit_price') ?> (SAR)</th>
      <th><?= t('pdf.discount') ?></th>
      <th><?= t('user.invoices.line_total') ?></th>
      <th></th>
    </tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_code[]" placeholder="e.g. ITM-001" maxlength="50"></td>
        <td><input type="text" name="item_description[]" placeholder="e.g. Mobilization payment (30%)" maxlength="255"></td>
        <td><input type="text" name="item_unit[]" placeholder="e.g. pc, m², hr" maxlength="20"></td>
        <td><input type="number" inputmode="decimal" step="0.01" min="0.01" max="99999999.99" name="item_qty[]" value="1" class="qty"></td>
        <td><input type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" name="item_price[]" value="0" class="cost"></td>
        <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999999.99" name="item_discount[]" value="0" class="line-discount"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  <div style="display:flex;gap:8px;">
    <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.invoices.add_line_item') ?></button>
    <button type="button" id="open-library-picker" class="btn btn-sm btn-outline"><?= t('user.invoices.pull_from_library') ?></button>
  </div>

  <div class="form-row" style="margin-top:14px;align-items:end;">
    <div class="form-group" style="margin:0;">
      <label><input type="checkbox" name="apply_vat" id="apply-vat" value="1" checked style="width:auto;display:inline-block;"> <?= t('user.invoices.apply_vat') ?> (<?= e((string)$vatRate) ?>%)</label>
    </div>
    <div class="form-group" style="margin:0;">
      <label><?= t('user.invoices.retention_withheld_percent') ?></label>
      <input type="number" inputmode="decimal" step="0.01" min="0" max="100" name="retention_percent" id="retention-percent" value="<?= e((string)$defaultRetentionPercent) ?>">
      <p class="help-text"><?= t('user.invoices.retention_hint') ?></p>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('pdf.discount') ?> (<?= t('common.total') ?>)</label>
      <input type="number" inputmode="decimal" step="0.01" min="0" max="9999999999.99" name="discount_amount" id="header-discount" value="0">
    </div>
    <div class="form-group">
      <label><?= t('pdf.other_charges') ?></label>
      <input type="number" inputmode="decimal" step="0.01" min="0" max="9999999999.99" name="other_charges" id="other-charges" value="0">
    </div>
  </div>

  <div style="text-align:right;font-size:14px;color:var(--muted);">
    <?= t('common.subtotal') ?>: <span id="grand-subtotal">0.00</span> SAR<br>
    <?= t('common.vat') ?>: <span id="grand-vat">0.00</span> SAR<br>
    <?= t('user.invoices.retention_withheld') ?> <span id="grand-retention">0.00</span> SAR
  </div>
  <div class="total-row"><?= t('common.total') ?>: <span id="grand-total">0.00</span> SAR</div>
  <p class="help-text" style="text-align:right;"><?= t('user.invoices.net_payable_now') ?> <strong><span id="grand-net">0.00</span> SAR</strong></p>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;"><?= t('user.invoices.create_invoice') ?></button>
</form>

@include('app.partials.library-picker')

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');
  const grandSubtotal = document.getElementById('grand-subtotal');
  const grandVat = document.getElementById('grand-vat');
  const grandRetention = document.getElementById('grand-retention');
  const grandNet = document.getElementById('grand-net');
  const applyVat = document.getElementById('apply-vat');
  const retentionPercentInput = document.getElementById('retention-percent');
  const headerDiscountInput = document.getElementById('header-discount');
  const otherChargesInput = document.getElementById('other-charges');
  const vatRate = <?= json_encode($vatRate) ?>;

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="item_code[]" placeholder="e.g. ITM-001" maxlength="50"></td>
      <td><input type="text" name="item_description[]" placeholder="Description" maxlength="255"></td>
      <td><input type="text" name="item_unit[]" placeholder="e.g. pc, m², hr" maxlength="20"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0.01" max="99999999.99" name="item_qty[]" value="1" class="qty"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" max="99999999.99" name="item_price[]" value="0" class="cost"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999999.99" name="item_discount[]" value="0" class="line-discount"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  // Live preview only — InvoiceController::store() is the authoritative
  // calculation (gross subtotal, minus every line discount and the header
  // discount, plus other charges, THEN VAT on what's left). Mirrored here
  // so what the person sees before submitting matches what gets saved.
  function recalc() {
    let subtotal = 0;
    let lineDiscounts = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineDiscount = Math.max(0, parseFloat(tr.querySelector('.line-discount').value) || 0);
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      subtotal += lineTotal;
      lineDiscounts += lineDiscount;
    });
    const headerDiscount = Math.max(0, parseFloat(headerDiscountInput.value) || 0);
    const otherCharges = Math.max(0, parseFloat(otherChargesInput.value) || 0);
    const taxable = Math.max(0, subtotal - lineDiscounts - headerDiscount) + otherCharges;
    const vat = applyVat.checked ? taxable * vatRate / 100 : 0;
    const total = taxable + vat;
    const retentionPct = Math.min(100, Math.max(0, parseFloat(retentionPercentInput.value) || 0));
    const retention = subtotal * retentionPct / 100;
    grandSubtotal.textContent = subtotal.toFixed(2);
    grandVat.textContent = vat.toFixed(2);
    grandRetention.textContent = retention.toFixed(2);
    grandTotal.textContent = total.toFixed(2);
    grandNet.textContent = (total - retention).toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  applyVat.addEventListener('change', recalc);
  retentionPercentInput.addEventListener('input', recalc);
  headerDiscountInput.addEventListener('input', recalc);
  otherChargesInput.addEventListener('input', recalc);
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
    tr.querySelector('.cost').value = e.detail.unitCost;
    recalc();
  });

  recalc();
})();
</script>

@endsection
