@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.reports.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/reports"><?= t('user.reports.tab_performance') ?></a>
  <a href="/app/reports/profit"><?= t('user.reports.tab_cost_variance') ?></a>
  <a href="/app/reports/tax"><?= t('user.reports.tab_tax') ?></a>
  <a href="/app/reports/retention"><?= t('user.reports.tab_retention') ?></a>
  <a href="/app/reports/cash-flow"><?= t('user.reports.tab_cash_flow') ?></a>
  <a href="/app/reports/accounting-export" class="active"><?= t('user.reports.tab_accounting_export') ?></a>
</div>

<div class="card">
  <h3><?= t('user.reports.accounting_export_title') ?></h3>
  <p class="help-text"><?= t('user.reports.accounting_export_hint') ?></p>

  <form method="GET" action="/app/reports/accounting-export/download">
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.reports.date_from') ?></label>
        <input type="date" name="date_from" required value="<?= e($dateFrom) ?>">
      </div>
      <div class="form-group">
        <label><?= t('user.reports.date_to') ?></label>
        <input type="date" name="date_to" required value="<?= e($dateTo) ?>">
      </div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('user.reports.download_csv') ?></button>
  </form>

  <div class="help-text" style="margin-top:16px">
    <?= t('user.reports.accounting_export_columns') ?><br>
    <?= t('user.reports.accounting_export_scope_note') ?>
  </div>
</div>
@endsection
