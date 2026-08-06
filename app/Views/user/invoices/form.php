<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>New Invoice</h1>
  <a href="/app/invoices" class="btn btn-light">← Back to invoices</a>
</div>

<form method="post" action="/app/invoices" class="card" style="max-width:820px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Invoice number</label><input type="text" name="invoice_number" value="<?= View::e($nextNumber) ?>"></div>
    <div class="form-group"><label>Due date</label><input type="date" name="due_date"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>Client</label>
      <select name="client_id">
        <option value="">— No client —</option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>Project</label>
      <select name="project_id">
        <option value="">— None —</option>
        <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
  </div>

  <label>Line items</label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:50%">Description</th><th>Qty</th><th>Unit price (SAR)</th><th>Line total</th><th></th></tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_description[]" placeholder="e.g. Mobilization payment (30%)"></td>
        <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
        <td><input type="number" step="0.01" name="item_price[]" value="0" class="cost"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  <div style="display:flex;gap:8px;">
    <button type="button" id="add-row" class="btn btn-sm btn-outline">+ Add line item</button>
    <button type="button" id="open-library-picker" class="btn btn-sm btn-outline">📚 Pull from library</button>
  </div>

  <div class="form-group" style="margin-top:14px;">
    <label><input type="checkbox" name="apply_vat" id="apply-vat" value="1" checked style="width:auto;display:inline-block;"> Apply VAT (<?= View::e((string)$vatRate) ?>%)</label>
  </div>

  <div style="text-align:right;font-size:14px;color:var(--muted);">
    Subtotal: <span id="grand-subtotal">0.00</span> SAR<br>
    VAT: <span id="grand-vat">0.00</span> SAR
  </div>
  <div class="total-row">Total: <span id="grand-total">0.00</span> SAR</div>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;">Create invoice</button>
</form>

<?php require BASE_PATH . '/app/Views/user/partials/library-picker.php'; ?>

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
