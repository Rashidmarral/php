@extends('layouts.app')

@section('content')
<?php use App\Core\View; use App\Core\Csrf; $isEdit = $lead !== null; ?>
<div class="page-head">
  <h1><?= $isEdit ? t('user.leads.edit_title') : t('user.leads.new_title') ?></h1>
  <a href="/app/leads" class="btn btn-light"><?= t('user.leads.back_to_leads') ?></a>
</div>

<form method="post" action="<?= $isEdit ? '/app/leads/' . $lead['id'] : '/app/leads' ?>" class="card" style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name') ?></label><input type="text" name="name" value="<?= e($lead['name'] ?? '') ?>" required></div>
    <div class="form-group"><label><?= t('user.leads.company_name_en') ?></label><input type="text" name="company_name" value="<?= e($lead['company_name'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.leads.company_name_ar') ?></label><input type="text" name="company_name_ar" dir="rtl" value="<?= e($lead['company_name_ar'] ?? '') ?>" placeholder="اسم الشركة بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= e($lead['email'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" value="<?= e($lead['phone'] ?? '') ?>" placeholder="+966 5x xxx xxxx"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.leads.source_col') ?></label>
      <select name="source">
        <?php foreach ($sources as $s): ?>
          <option value="<?= $s ?>" <?= ($lead['source'] ?? '') === $s ? 'selected' : '' ?>><?= ucwords(str_replace('_', ' ', $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.leads.estimated_value') ?></label><input type="number" step="0.01" name="estimated_value" value="<?= e((string)($lead['estimated_value'] ?? 0)) ?>"></div>
  </div>
  <?php if ($isEdit): ?>
    <div class="form-group">
      <label><?= t('common.status') ?></label>
      <select name="status">
        <?php foreach ($statuses as $s): ?>
          <option value="<?= $s ?>" <?= $lead['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>
  <div class="form-group"><label><?= t('common.notes') ?></label><textarea name="notes" rows="4"><?= e($lead['notes'] ?? '') ?></textarea></div>
  <button type="submit" class="btn btn-primary"><?= $isEdit ? t('common.save_changes') : t('user.leads.add_lead') ?></button>
</form>

@endsection
