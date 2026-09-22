@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <a href="/app/zakat" class="help-text"><?= t('user.zakat.back_to_list') ?></a>
    <h1 style="margin-top:6px;">🕌 <?= t('user.zakat.new_estimate') ?></h1>
  </div>
</div>

<div class="alert" style="background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
  ⚠️ <?= t('user.zakat.disclaimer') ?>
</div>

<form method="post" action="/app/zakat" class="card" style="max-width:720px;">
  <?= csrf_field() ?>

  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.zakat.period_end_date') ?></label>
      <input type="date" name="period_end_date" required value="<?= e(now()->format('Y-m-d')) ?>">
      <div class="help-text"><?= t('user.zakat.period_end_date_hint') ?></div>
    </div>
    <div class="form-group">
      <label><?= t('user.zakat.rate_type') ?></label>
      <select name="rate_type">
        <option value="hijri"><?= t('user.zakat.rate_hijri') ?></option>
        <option value="gregorian"><?= t('user.zakat.rate_gregorian') ?></option>
      </select>
      <div class="help-text"><?= t('user.zakat.rate_type_hint') ?></div>
    </div>
  </div>

  <div class="form-group">
    <label><?= t('user.zakat.equity_amount') ?></label>
    <input type="number" step="0.01" name="equity_amount" value="0" required>
    <div class="help-text"><?= t('user.zakat.equity_amount_hint') ?></div>
  </div>

  <div class="form-group">
    <label><?= t('user.zakat.long_term_liabilities') ?></label>
    <input type="number" step="0.01" name="long_term_liabilities" value="0">
    <div class="help-text"><?= t('user.zakat.long_term_liabilities_hint') ?></div>
  </div>

  <div class="form-group">
    <label><?= t('user.zakat.net_fixed_assets') ?></label>
    <input type="number" step="0.01" name="net_fixed_assets" value="0">
    <div class="help-text"><?= t('user.zakat.net_fixed_assets_hint') ?></div>
  </div>

  <div class="form-group">
    <label><?= t('user.zakat.other_deductions') ?></label>
    <input type="number" step="0.01" name="other_deductions" value="0">
    <div class="help-text"><?= t('user.zakat.other_deductions_hint') ?></div>
  </div>

  <div class="form-group">
    <label><?= t('common.notes') ?></label>
    <textarea name="notes" rows="3"></textarea>
  </div>

  <button type="submit" class="btn btn-primary"><?= t('user.zakat.calculate') ?></button>
</form>

@endsection
