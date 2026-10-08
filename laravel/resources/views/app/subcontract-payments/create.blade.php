@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/subcontracts/<?= $subcontract['id'] ?>">&larr; <?= e($subcontract['title']) ?></a></p>
    <h1><?= t('user.subcontract_payments.create_title') ?> #<?= $nextNumber ?></h1>
  </div>
</div>

<form method="post" action="/app/subcontracts/<?= $subcontract['id'] ?>/payments" id="payment-form">
  <?= csrf_field() ?>

  <div class="card" style="max-width:600px;">
    <div class="form-row">
      <div class="form-group"><label><?= t('user.subcontract_payments.date') ?></label><input type="date" name="payment_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label><?= t('user.subcontract_payments.retention_percent') ?></label><input type="number" step="0.01" min="0" max="100" name="retention_percent" id="retention-percent" value="<?= e((string)$defaultRetentionPercent) ?>"></div>
    </div>

    <p class="help-text">
      <?= t('user.subcontracts.contract_value') ?>: <?= money($contractValue) ?> ·
      <?= t('user.subcontract_payments.previous_cumulative') ?>: <?= money($previousCumulative) ?>
    </p>

    <div class="form-group">
      <label><?= t('user.subcontract_payments.cumulative_value') ?></label>
      <input type="number" step="0.01" name="cumulative_value" id="cumulative-value" value="<?= e((string)$previousCumulative) ?>" min="<?= e((string)$previousCumulative) ?>" max="<?= e((string)$contractValue) ?>" required>
    </div>

    <div class="form-group"><label><?= t('user.subcontract_payments.notes') ?></label><textarea name="notes" rows="2"></textarea></div>
  </div>

  <div class="card" style="margin-top:20px;max-width:420px;">
    <div class="breakdown" style="font-size:13.5px;">
      <div><span><?= t('user.subcontract_payments.this_period_value') ?></span><span id="calc-period">0.00</span></div>
      <div><span><?= t('user.subcontract_payments.retention') ?> (<span id="retention-pct-label"><?= e((string)$defaultRetentionPercent) ?></span>%)</span><span id="calc-retention">0.00</span></div>
    </div>
    <div class="total-row" style="margin-top:8px;"><?= t('user.subcontract_payments.net_payable') ?>: <span id="calc-net">0.00</span> SAR</div>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:20px;"><?= t('common.save_as_draft') ?></button>
</form>

<script>
(function() {
  var previous = <?= (float) $previousCumulative ?>;
  var cumulativeInput = document.getElementById('cumulative-value');
  var retentionInput = document.getElementById('retention-percent');

  function recalc() {
    var cumulative = parseFloat(cumulativeInput.value) || 0;
    var periodValue = cumulative - previous;
    var retentionPct = parseFloat(retentionInput.value) || 0;
    var retentionAmount = periodValue * retentionPct / 100;
    var net = periodValue - retentionAmount;

    document.getElementById('retention-pct-label').textContent = retentionPct.toFixed(2);
    document.getElementById('calc-period').textContent = periodValue.toFixed(2);
    document.getElementById('calc-retention').textContent = retentionAmount.toFixed(2);
    document.getElementById('calc-net').textContent = net.toFixed(2);
  }

  cumulativeInput.addEventListener('input', recalc);
  retentionInput.addEventListener('input', recalc);
  recalc();
})();
</script>

@endsection
