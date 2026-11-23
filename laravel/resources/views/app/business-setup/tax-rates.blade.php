@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.business_setup.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types"><?= t('user.business_setup.tab_building_types') ?></a>
  <a href="/app/business-setup/contact-types"><?= t('user.business_setup.tab_contact_types') ?></a>
  <a href="/app/business-setup/client-types"><?= t('user.business_setup.tab_client_types') ?></a>
  <a href="/app/business-setup/units-of-measure"><?= t('user.business_setup.tab_units') ?></a>
  <a href="/app/business-setup/tax-rates" class="active"><?= t('user.business_setup.tab_tax_rates') ?></a>
  <a href="/app/business-setup/compliance"><?= t('user.business_setup.tab_compliance') ?></a>
</div>

<?php if (auth()->user()->can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;"><?= t('user.business_setup.tab_tax_rates') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.business_setup.tax_rates_hint') ?></p>
  <form method="post" action="/app/business-setup/tax-rates" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 120px 120px auto;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_en') ?></label><input type="text" name="name" placeholder="e.g. Standard VAT" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" placeholder="ضريبة القيمة المضافة"></div>
    <div class="form-group" style="margin:0;"><label><?= t('user.business_setup.rate_percent') ?></label><input type="number" step="0.01" name="rate_percent" value="15"></div>
    <div class="form-group" style="margin:0;"><label><input type="checkbox" name="is_default" value="1" style="width:auto;display:inline-block;"> <?= t('common.default') ?></label></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card"><p><?= t('user.business_setup.no_tax_rates_hint') ?></p></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name_en') ?></th><th><?= t('common.name_ar') ?></th><th><?= t('user.business_setup.rate_percent') ?></th><th><?= t('common.default') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'tax-' . $r['id']; ?>
      <?php if (auth()->user()->can('manage_business_setup')): ?>
        <form id="<?= $fid ?>" method="post" action="/app/business-setup/tax-rates/<?= $r['id'] ?>"><?= csrf_field() ?></form>
      <?php endif; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="text" name="name" value="<?= e($r['name']) ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:160px;"></td>
        <td><input form="<?= $fid ?>" type="text" name="name_ar" dir="rtl" value="<?= e($r['name_ar'] ?? '') ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:160px;"></td>
        <td><input form="<?= $fid ?>" type="number" step="0.01" name="rate_percent" value="<?= e((string)$r['rate_percent']) ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="width:100px;"></td>
        <td><input form="<?= $fid ?>" type="checkbox" name="is_default" value="1" <?= $r['is_default'] ? 'checked' : '' ?> <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?>></td>
        <td style="display:flex;gap:6px;">
          <?php if (auth()->user()->can('manage_business_setup')): ?>
            <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
            <form method="post" action="/app/business-setup/tax-rates/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= t('user.business_setup.delete_tax_rate_confirm') ?>');" style="display:inline;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
