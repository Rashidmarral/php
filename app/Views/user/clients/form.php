<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= $client ? t('user.clients.edit_title') : t('user.clients.new_title') ?></h1>
  <a href="/app/clients" class="btn btn-light"><?= t('user.clients.back_to_clients') ?></a>
</div>

<form method="post" action="<?= $client ? '/app/clients/' . $client['id'] : '/app/clients' ?>" class="card" style="max-width:600px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required value="<?= View::e($client['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($client['name_ar'] ?? '') ?>" placeholder="الاسم بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= View::e($client['email'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" placeholder="+966 5x xxx xxxx" value="<?= View::e($client['phone'] ?? '') ?>"></div>
  </div>
  <div class="form-group"><label><?= t('common.address') ?></label><textarea name="address"><?= View::e($client['address'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $client ? t('common.save_changes') : t('user.clients.add_client') ?></button>
</form>
