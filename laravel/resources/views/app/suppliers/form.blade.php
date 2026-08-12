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
  <div class="form-group"><label><?= t('common.notes') ?></label><textarea name="notes"><?= e($supplier['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $supplier ? t('common.save_changes') : t('user.suppliers.add_supplier') ?></button>
</form>

@endsection
