@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('credit_note.new_title') ?></h1>
  <a href="/app/credit-notes" class="btn btn-light"><?= t('credit_note.back_to_notes') ?></a>
</div>

<p class="help-text" style="max-width:820px;">
  <?= t('credit_note.against_invoice') ?> <strong><?= e($invoice['invoice_number']) ?></strong>
  · <?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?>
  · <?= t('credit_note.remaining_creditable') ?>: <strong id="remaining-creditable"><?= money((float)$remainingCreditable) ?></strong>
</p>

<form method="post" action="/app/credit-notes" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
  <div class="form-row">
    <div class="form-group"><label><?= t('credit_note.note_number') ?></label><input type="text" value="<?= e($nextNumber) ?>" disabled></div>
    <div class="form-group"><label><?= t('credit_note.issue_date') ?></label><input type="date" name="issue_date" value="<?= e(date('Y-m-d')) ?>"></div>
  </div>
  <div class="form-group">
    <label><?= t('credit_note.reason') ?></label>
    <input type="text" name="reason" placeholder="<?= t('credit_note.reason_placeholder') ?>">
  </div>

  <label><?= t('user.invoices.line_items') ?></label>
  <table class="line-items" id="items-table">
    <thead><tr><th style="width:50%"><?= t('common.description') ?></th><th><?= t('common.qty') ?></th><th><?= t('common.unit_price') ?> (SAR)</th><th><?= t('user.invoices.line_total') ?></th><th></th></tr></thead>
    <tbody id="items-body">
      <?php foreach ($items as $it): ?>
        <tr>
          <td><input type="text" name="item_description[]" value="<?= e($it['description']) ?>"></td>
          <td><input type="number" step="0.01" name="item_qty[]" value="<?= e($it['qty']) ?>" class="qty"></td>
          <td><input type="number" step="0.01" name="item_price[]" value="<?= e($it['unit_price']) ?>" class="cost"></td>
          <td class="line-total">0.00</td>
          <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <button type="button" id="add-row" class="btn btn-sm btn-outline"><?= t('user.invoices.add_line_item') ?></button>

  <div style="text-align:right;font-size:14px;color:var(--muted);margin-top:14px;">
    <?= t('common.subtotal') ?>: <span id="grand-subtotal">0.00</span> SAR<br>
    <?= t('common.vat') ?> (<?= e((string)$invoice['vat_rate']) ?>%): <span id="grand-vat">0.00</span> SAR
  </div>
  <div class="total-row"><?= t('common.total') ?>: <span id="grand-total">0.00</span> SAR</div>
  <p class="help-text" id="over-limit-warning" style="text-align:right;color:var(--danger);display:none;"><?= t('credit_note.exceeds_remaining') ?></p>

  <button type="submit" class="btn btn-primary" style="margin-top:16px;"><?= t('credit_note.issue') ?></button>
</form>

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');
  const grandSubtotal = document.getElementById('grand-subtotal');
  const grandVat = document.getElementById('grand-vat');
  const warning = document.getElementById('over-limit-warning');
  const vatRate = <?= json_encode((float)$invoice['vat_rate']) ?>;
  const remaining = <?= json_encode((float)$remainingCreditable) ?>;

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
    const vat = subtotal * vatRate / 100;
    const total = subtotal + vat;
    grandSubtotal.textContent = subtotal.toFixed(2);
    grandVat.textContent = vat.toFixed(2);
    grandTotal.textContent = total.toFixed(2);
    warning.style.display = (total - remaining > 0.01) ? 'block' : 'none';
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
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
