@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.purchase_orders.new_title') ?></h1>
  <a href="/app/projects/<?= $project['id'] ?>" class="btn btn-light"><?= t('user.purchase_orders.back_to_project') ?></a>
</div>

<form method="post" action="/app/projects/<?= $project['id'] ?>/purchase-orders" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.purchase_orders.number_preview', ['number' => $nextNumber]) ?></p>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('common.supplier') ?></label>
      <select name="supplier_id">
        <option value="">—</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.projects.issue_date') ?></label><input type="date" name="issue_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group"><label><?= t('user.purchase_orders.expected_delivery_date') ?></label><input type="date" name="expected_delivery_date"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.purchase_orders.currency') ?></label>
      <select name="currency" id="po-currency">
        <?php foreach (['SAR', 'USD', 'EUR', 'GBP', 'AED', 'CNY'] as $cur): ?>
          <option value="<?= $cur ?>" <?= $cur === 'SAR' ? 'selected' : '' ?>><?= $cur ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help-text" style="margin-top:4px;"><?= t('user.purchase_orders.currency_hint') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('user.purchase_orders.exchange_rate_to_sar') ?></label>
      <input type="number" step="0.0001" min="0.0001" name="exchange_rate_to_sar" id="po-exchange-rate" value="1.0000" disabled>
    </div>
  </div>
  <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>

  <label><?= t('user.invoices.line_items') ?></label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:50%"><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?> (SAR)</th><th><?= t('user.invoices.line_total') ?></th><th></th></tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_description[]" placeholder="e.g. 50 bags of cement"></td>
        <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
        <td><input type="number" step="0.01" name="item_price[]" value="0" class="cost"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.invoices.add_line_item') ?></button>

  <div class="form-row" style="margin-top:14px;align-items:end;">
    <div class="form-group" style="margin:0;">
      <label><input type="checkbox" name="apply_vat" id="apply-vat" value="1" checked style="width:auto;display:inline-block;"> <?= t('user.invoices.apply_vat') ?> (<?= e((string)$vatRate) ?>%)</label>
    </div>
  </div>

  <div style="text-align:right;font-size:14px;color:var(--muted);">
    <?= t('common.subtotal') ?>: <span id="grand-subtotal">0.00</span> SAR<br>
    <?= t('common.vat') ?>: <span id="grand-vat">0.00</span> SAR
  </div>
  <div class="total-row"><?= t('common.total') ?>: <span id="grand-total">0.00</span> <span id="grand-total-currency">SAR</span></div>
  <p class="help-text" id="sar-equivalent-row" style="display:none;margin-top:-8px;"><?= t('user.purchase_orders.sar_equivalent') ?>: <span id="grand-total-sar">0.00</span> SAR</p>

  <div style="display:flex;gap:8px;margin-top:16px;">
    <button type="submit" name="status" value="draft" class="btn btn-light"><?= t('user.purchase_orders.save_draft') ?></button>
    <button type="submit" name="status" value="issued" class="btn btn-primary"><?= t('user.purchase_orders.issue_po') ?></button>
  </div>
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
  const currencySelect = document.getElementById('po-currency');
  const rateInput = document.getElementById('po-exchange-rate');
  const grandTotalCurrency = document.getElementById('grand-total-currency');
  const sarEquivalentRow = document.getElementById('sar-equivalent-row');
  const grandTotalSar = document.getElementById('grand-total-sar');

  function syncCurrencyFields() {
    const isSar = currencySelect.value === 'SAR';
    rateInput.disabled = isSar;
    if (isSar) rateInput.value = '1.0000';
    grandTotalCurrency.textContent = currencySelect.value;
    sarEquivalentRow.style.display = isSar ? 'none' : 'block';
    recalc();
  }

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
    const total = subtotal + vat;
    grandSubtotal.textContent = subtotal.toFixed(2);
    grandVat.textContent = vat.toFixed(2);
    grandTotal.textContent = total.toFixed(2);
    const rate = parseFloat(rateInput.value) || 1;
    grandTotalSar.textContent = (total * rate).toFixed(2);
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

  currencySelect.addEventListener('change', syncCurrencyFields);
  rateInput.addEventListener('input', recalc);
  syncCurrencyFields();
})();
</script>
@endsection
