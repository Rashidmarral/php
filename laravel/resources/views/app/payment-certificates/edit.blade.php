@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/payment-certificates/<?= $certificate['id'] ?>">&larr; <?= t('user.payment_certificates.title') ?> #<?= $certificate['certificate_number'] ?></a></p>
    <h1><?= t('user.payment_certificates.edit_title', ['number' => $certificate['certificate_number']]) ?></h1>
  </div>
</div>

<form method="post" action="/app/payment-certificates/<?= $certificate['id'] ?>" id="cert-form">
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom:20px;">
    <div class="form-row">
      <div class="form-group"><label><?= t('user.payment_certificates.date') ?></label><input type="date" name="certificate_date" value="<?= e($certificate['certificate_date']) ?>"></div>
      <div class="form-group"><label><?= t('user.payment_certificates.period_from') ?></label><input type="date" name="period_from" value="<?= e($certificate['period_from'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('user.payment_certificates.period_to') ?></label><input type="date" name="period_to" value="<?= e($certificate['period_to'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.payment_certificates.retention_percent') ?></label><input type="number" step="0.01" min="0" max="100" name="retention_percent" id="retention-percent" value="<?= e((string)$certificate['retention_percent']) ?>"></div>
      <?php if ((float)$project['advance_payment_amount'] > 0): ?>
        <div class="form-group">
          <label><?= t('user.payment_certificates.advance_recovery_percent') ?></label>
          <input type="number" step="0.01" min="0" max="100" name="advance_recovery_percent" id="advance-recovery-percent" value="<?= e((string)($certificate['advance_recovery_percent'] ?? 0)) ?>">
        </div>
      <?php endif; ?>
    </div>
    <div class="form-group"><label><?= t('user.payment_certificates.notes') ?></label><textarea name="notes" rows="2"><?= e($certificate['notes'] ?? '') ?></textarea></div>
  </div>

  <div class="card">
    <div style="overflow-x:auto;">
    <table class="data" id="boq-table">
      <thead>
        <tr>
          <th><?= t('user.boq.item_number') ?></th>
          <th><?= t('common.description_en') ?></th>
          <th><?= t('user.boq.uom') ?></th>
          <th><?= t('user.boq.qty') ?></th>
          <th><?= t('user.boq.unit_price') ?></th>
          <th><?= t('user.payment_certificates.previous_cumulative') ?></th>
          <th style="min-width:140px;"><?= t('user.payment_certificates.this_cumulative') ?></th>
          <th><?= t('user.payment_certificates.this_period_qty') ?></th>
          <th><?= t('user.payment_certificates.this_period_value') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php $lastSection = null; ?>
        <?php foreach ($rows as $row): ?>
          <?php if (!empty($row['section_title']) && $row['section_title'] !== $lastSection): $lastSection = $row['section_title']; ?>
            <tr><td colspan="9" style="background:var(--bg);font-weight:700;"><?= e($row['section_title']) ?></td></tr>
          <?php endif; ?>
          <tr class="boq-row" data-unit-price="<?= $row['contract_unit_price'] ?>" data-previous="<?= $row['previous_cumulative_qty'] ?>" data-contract-qty="<?= $row['contract_qty'] ?>">
            <td><input type="hidden" name="boq_item_id[]" value="<?= $row['boq_item_id'] ?>"><?= e($row['item_number'] ?? '') ?></td>
            <td><?= e($row['description']) ?></td>
            <td><?= e($row['uom']) ?></td>
            <td class="num"><?= number_format($row['contract_qty'], 2) ?></td>
            <td class="num"><?= number_format($row['contract_unit_price'], 2) ?></td>
            <td class="num prev-cum"><?= number_format($row['previous_cumulative_qty'], 2) ?></td>
            <td><input type="number" step="0.01" name="cumulative_qty[]" class="cumulative-qty" value="<?= $row['cumulative_qty'] ?>" min="<?= $row['previous_cumulative_qty'] ?>" max="<?= $row['contract_qty'] ?>" style="width:110px;"></td>
            <td class="num period-qty">0.00</td>
            <td class="num period-value">0.00</td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </div>

  <div class="card" style="margin-top:20px;max-width:420px;margin-inline-start:auto;">
    <div class="breakdown" style="font-size:13.5px;">
      <div><span><?= t('user.payment_certificates.gross') ?></span><span id="calc-gross">0.00</span></div>
      <div><span><?= t('user.payment_certificates.retention') ?></span><span id="calc-retention">0.00</span></div>
      <?php if ((float)$project['advance_payment_amount'] > 0): ?>
        <div><span><?= t('user.payment_certificates.advance_recovery') ?></span><span id="calc-advance">0.00</span></div>
      <?php endif; ?>
    </div>
    <div class="total-row" style="margin-top:8px;"><?= t('user.payment_certificates.net_payable') ?>: <span id="calc-net">0.00</span> SAR</div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:20px;"><?= t('common.save_changes') ?></button>
</form>

<script>
(function() {
  var rows = document.querySelectorAll('.boq-row');
  var retentionInput = document.getElementById('retention-percent');
  var advanceInput = document.getElementById('advance-recovery-percent');

  function recalc() {
    var gross = 0;
    rows.forEach(function(tr) {
      var unitPrice = parseFloat(tr.dataset.unitPrice) || 0;
      var previous = parseFloat(tr.dataset.previous) || 0;
      var input = tr.querySelector('.cumulative-qty');
      var cumulative = parseFloat(input.value) || 0;
      var periodQty = cumulative - previous;
      var periodValue = periodQty * unitPrice;
      tr.querySelector('.period-qty').textContent = periodQty.toFixed(2);
      tr.querySelector('.period-value').textContent = periodValue.toFixed(2);
      gross += periodValue;
    });

    var retentionPct = retentionInput ? (parseFloat(retentionInput.value) || 0) : 0;
    var retentionAmount = gross * retentionPct / 100;

    var advanceAmountDeducted = 0;
    if (advanceInput) {
      var advancePct = parseFloat(advanceInput.value) || 0;
      advanceAmountDeducted = gross * advancePct / 100;
      document.getElementById('calc-advance').textContent = advanceAmountDeducted.toFixed(2);
    }

    var net = gross - retentionAmount - advanceAmountDeducted;
    document.getElementById('calc-gross').textContent = gross.toFixed(2);
    document.getElementById('calc-retention').textContent = retentionAmount.toFixed(2);
    document.getElementById('calc-net').textContent = net.toFixed(2);
  }

  rows.forEach(function(tr) { tr.querySelector('.cumulative-qty').addEventListener('input', recalc); });
  if (retentionInput) retentionInput.addEventListener('input', recalc);
  if (advanceInput) advanceInput.addEventListener('input', recalc);
  recalc();
})();
</script>

@endsection
