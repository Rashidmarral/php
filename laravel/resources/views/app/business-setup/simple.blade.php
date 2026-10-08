@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.business_setup.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types" class="<?= $type === 'building-types' ? 'active' : '' ?>"><?= t('user.business_setup.tab_building_types') ?></a>
  <a href="/app/business-setup/contact-types" class="<?= $type === 'contact-types' ? 'active' : '' ?>"><?= t('user.business_setup.tab_contact_types') ?></a>
  <a href="/app/business-setup/client-types" class="<?= $type === 'client-types' ? 'active' : '' ?>"><?= t('user.business_setup.tab_client_types') ?></a>
  <a href="/app/business-setup/units-of-measure"><?= t('user.business_setup.tab_units') ?></a>
  <a href="/app/business-setup/tax-rates"><?= t('user.business_setup.tab_tax_rates') ?></a>
  <a href="/app/business-setup/compliance"><?= t('user.business_setup.tab_compliance') ?></a>
</div>

<?php if (auth()->user()->can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;"><?= e($config['label']) ?></h3>
  <form method="post" action="/app/business-setup/<?= e($type) ?>" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 140px auto;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_en') ?></label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" placeholder="بالعربية"></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
  </form>
  <?php if (empty($rows)): ?>
    <form method="post" action="/app/business-setup/<?= e($type) ?>/load-defaults" style="margin-top:12px;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline btn-sm"><?= t('user.business_setup.load_defaults') ?></button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card"><p><?= t('user.business_setup.no_items_yet', ['label' => e(strtolower($config['label']))]) ?></p></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name_en') ?></th><th><?= t('common.name_ar') ?></th><th><?= t('common.sort_order') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'row-' . $r['id']; ?>
      <?php if (auth()->user()->can('manage_business_setup')): ?>
        <form id="<?= $fid ?>" method="post" action="/app/business-setup/<?= e($type) ?>/<?= $r['id'] ?>"><?= csrf_field() ?></form>
      <?php endif; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="text" name="name" value="<?= e($r['name']) ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:180px;"></td>
        <td><input form="<?= $fid ?>" type="text" name="name_ar" dir="rtl" value="<?= e($r['name_ar'] ?? '') ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:180px;"></td>
        <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= e((string)$r['sort_order']) ?>" <?= auth()->user()->can('manage_business_setup') ? '' : 'disabled' ?> style="width:90px;"></td>
        <td style="display:flex;gap:6px;">
          <?php if (auth()->user()->can('manage_business_setup')): ?>
            <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
            <form method="post" action="/app/business-setup/<?= e($type) ?>/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= t('user.business_setup.delete_item_confirm') ?>');" style="display:inline;">
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
