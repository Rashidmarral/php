@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= $client ? t('user.clients.edit_title') : t('user.clients.new_title') ?></h1>
  <a href="/app/clients" class="btn btn-light"><?= t('user.clients.back_to_clients') ?></a>
</div>

<form method="post" action="<?= $client ? '/app/clients/' . $client['id'] : '/app/clients' ?>" class="card" style="max-width:600px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required value="<?= e($client['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($client['name_ar'] ?? '') ?>" placeholder="الاسم بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= e($client['email'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= e($client['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label><?= t('common.address') ?></label><textarea name="address"><?= e($client['address'] ?? '') ?></textarea></div>

  <h3 style="font-size:14px;margin-top:24px;"><?= t('user.clients.b2b_section') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.clients.b2b_hint') ?></p>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.clients.vat_number') ?></label><input type="text" name="vat_number" maxlength="15" placeholder="3XXXXXXXXXXXXX3" value="<?= e($client['vat_number'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.clients.cr_number') ?></label><input type="text" name="cr_number" value="<?= e($client['cr_number'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.building_number') ?></label><input type="text" name="building_number" maxlength="4" placeholder="1234" value="<?= e($client['building_number'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.settings.street_name') ?></label><input type="text" name="street_name" value="<?= e($client['street_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.district') ?></label><input type="text" name="district" value="<?= e($client['district'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.city') ?></label><input type="text" name="city" value="<?= e($client['city'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.settings.postal_code') ?></label><input type="text" name="postal_code" maxlength="5" placeholder="12345" value="<?= e($client['postal_code'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.settings.additional_number') ?></label><input type="text" name="additional_number" maxlength="4" placeholder="6789" value="<?= e($client['additional_number'] ?? '') ?>"></div>
  </div>

  <button type="submit" class="btn btn-primary"><?= $client ? t('common.save_changes') : t('user.clients.add_client') ?></button>
</form>

@endsection
