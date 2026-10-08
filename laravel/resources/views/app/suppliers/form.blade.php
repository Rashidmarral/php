@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= $supplier ? t('user.suppliers.edit_title') : t('user.suppliers.new_title') ?></h1>
  <a href="/app/suppliers" class="btn btn-light"><?= t('user.suppliers.back_to_suppliers') ?></a>
</div>

<form method="post" action="<?= $supplier ? '/app/suppliers/' . $supplier['id'] : '/app/suppliers' ?>" class="card" style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.suppliers.name_en') ?></label><input type="text" name="name" required value="<?= e($supplier['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.suppliers.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($supplier['name_ar'] ?? '') ?>" placeholder="اسم المورد"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.category') ?></label><input type="text" name="category" placeholder="e.g. Steel, Electrical, Concrete" value="<?= e($supplier['category'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.suppliers.contact_person') ?></label><input type="text" name="contact_name" value="<?= e($supplier['contact_name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= e($supplier['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= e($supplier['email'] ?? '') ?>"></div>
  <div class="form-group"><label><?= t('common.address') ?></label><input type="text" name="address" value="<?= e($supplier['address'] ?? '') ?>"></div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.suppliers.currency') ?></label>
      <select name="currency" id="supplier-currency">
        <?php $supplierCurrency = $supplier['currency'] ?? 'SAR'; ?>
        <?php foreach (['SAR', 'USD', 'EUR', 'GBP', 'AED', 'CNY'] as $cur): ?>
          <option value="<?= $cur ?>" <?= $supplierCurrency === $cur ? 'selected' : '' ?>><?= $cur ?></option>
        <?php endforeach; ?>
      </select>
      <p class="help-text" style="margin-top:4px;"><?= t('user.suppliers.currency_hint') ?></p>
    </div>
    <div class="form-group">
      <label><?= t('user.suppliers.exchange_rate_to_sar') ?></label>
      <input type="number" step="0.0001" min="0.0001" name="exchange_rate_to_sar" id="supplier-exchange-rate" value="<?= e((string) ($supplier['exchange_rate_to_sar'] ?? 1.0)) ?>">
    </div>
  </div>
  <div class="form-group"><label><?= t('common.notes') ?></label><textarea name="notes"><?= e($supplier['notes'] ?? '') ?></textarea></div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.suppliers.prequalification') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.suppliers.prequalification_hint') ?></p>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.suppliers.trade_category') ?></label><input type="text" name="trade_category" placeholder="e.g. Electrical, Plumbing, Steel Fabrication" value="<?= e($supplier['trade_category'] ?? '') ?>"></div>
    <div class="form-group">
      <label><?= t('user.settings.classification_grade') ?></label>
      <select name="classification_grade">
        <option value=""><?= t('user.settings.not_classified') ?></option>
        <?php foreach ($classificationGrades ?? ['1', '2', '3', '4', '5'] as $val): ?>
          <option value="<?= $val ?>" <?= ($supplier['classification_grade'] ?? '') === $val ? 'selected' : '' ?>><?= t('user.settings.classification_grade_option', ['n' => $val]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.suppliers.cr_number') ?></label><input type="text" name="cr_number" value="<?= e($supplier['cr_number'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.suppliers.vat_number') ?></label><input type="text" name="vat_number" value="<?= e($supplier['vat_number'] ?? '') ?>"></div>
  </div>
  <div class="form-group">
    <label style="display:inline-flex;align-items:center;gap:6px;">
      <input type="checkbox" name="is_approved_vendor" value="1" <?= !empty($supplier['is_approved_vendor']) ? 'checked' : '' ?>>
      <?= t('user.suppliers.is_approved_vendor') ?>
    </label>
  </div>
  <div class="form-group"><label><?= t('user.suppliers.approved_vendor_notes') ?></label><textarea name="approved_vendor_notes" placeholder="<?= t('user.suppliers.approved_vendor_notes_placeholder') ?>"><?= e($supplier['approved_vendor_notes'] ?? '') ?></textarea></div>

  <button type="submit" class="btn btn-primary"><?= $supplier ? t('common.save_changes') : t('user.suppliers.add_supplier') ?></button>
</form>

<script>
(function() {
  const currencySelect = document.getElementById('supplier-currency');
  const rateInput = document.getElementById('supplier-exchange-rate');

  function syncRateField() {
    const isSar = currencySelect.value === 'SAR';
    rateInput.disabled = isSar;
    if (isSar) rateInput.value = '1.0000';
  }

  currencySelect.addEventListener('change', syncRateField);
  syncRateField();
})();
</script>

@endsection
