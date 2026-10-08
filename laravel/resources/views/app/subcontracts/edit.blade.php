@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/subcontracts/<?= $subcontract['id'] ?>">&larr; <?= e($subcontract['title']) ?></a></p>
    <h1><?= t('user.subcontracts.edit_title') ?></h1>
  </div>
</div>

<form method="post" action="/app/subcontracts/<?= $subcontract['id'] ?>" class="card" style="max-width:820px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.subcontracts.subcontractor') ?></label>
      <select name="supplier_id" required>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>" <?= $s['id'] == $subcontract['supplier_id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" value="<?= e($subcontract['title']) ?>" required></div>
  </div>
  <div class="form-group"><label><?= t('user.subcontracts.description') ?></label><textarea name="description" rows="3"><?= e($subcontract['description'] ?? '') ?></textarea></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.subcontracts.contract_value') ?></label><input type="number" step="0.01" min="0.01" name="contract_value" value="<?= e((string)$subcontract['contract_value']) ?>" required></div>
    <div class="form-group"><label><?= t('user.subcontracts.retention_percent') ?></label><input type="number" step="0.01" min="0" max="100" name="retention_percent" value="<?= e((string)$subcontract['retention_percent']) ?>"></div>
  </div>
  <div class="form-row">
    <?php $scCurrency = $subcontract['currency'] ?? 'SAR'; ?>
    <div class="form-group">
      <label><?= t('user.subcontracts.currency') ?></label>
      <select name="currency" id="sc-currency">
        <?php foreach (['SAR', 'USD', 'EUR', 'GBP', 'AED', 'CNY'] as $cur): ?>
          <option value="<?= $cur ?>" <?= $scCurrency === $cur ? 'selected' : '' ?>><?= $cur ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help-text" style="margin-top:4px;"><?= t('user.subcontracts.currency_hint') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('user.subcontracts.exchange_rate_to_sar') ?></label>
      <input type="number" step="0.0001" min="0.0001" name="exchange_rate_to_sar" id="sc-exchange-rate" value="<?= e((string) ($subcontract['exchange_rate_to_sar'] ?? 1.0)) ?>" <?= $scCurrency === 'SAR' ? 'disabled' : '' ?>>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.status') ?></label>
      <select name="status">
        <?php foreach (\App\Models\Subcontract::STATUSES as $key => $label): ?>
          <option value="<?= $key ?>" <?= $subcontract['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.subcontracts.start_date') ?></label><input type="date" name="start_date" value="<?= e($subcontract['start_date'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.subcontracts.end_date') ?></label><input type="date" name="end_date" value="<?= e($subcontract['end_date'] ?? '') ?>"></div>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.save_changes') ?></button>
</form>

<script>
(function() {
  const currencySelect = document.getElementById('sc-currency');
  const rateInput = document.getElementById('sc-exchange-rate');

  function syncRateField() {
    const isSar = currencySelect.value === 'SAR';
    rateInput.disabled = isSar;
    if (isSar) rateInput.value = '1.0000';
  }

  currencySelect.addEventListener('change', syncRateField);
})();
</script>

@endsection
