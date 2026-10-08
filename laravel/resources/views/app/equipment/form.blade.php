@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= $equipment ? t('user.equipment.edit_title') : t('user.equipment.new_title') ?></h1>
  <a href="/app/equipment" class="btn btn-light"><?= t('user.equipment.back_to_equipment') ?></a>
</div>

<form method="post" action="<?= $equipment ? '/app/equipment/' . $equipment['id'] : '/app/equipment' ?>" class="card" style="max-width:640px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required value="<?= e($equipment['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($equipment['name_ar'] ?? '') ?>" placeholder="اسم المعدة"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.equipment.asset_number') ?></label><input type="text" name="asset_number" placeholder="e.g. EX-204" value="<?= e($equipment['asset_number'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('common.category') ?></label><input type="text" name="category" placeholder="e.g. Excavator, Generator, Scaffolding" value="<?= e($equipment['category'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.equipment.ownership_type') ?></label>
      <select name="ownership_type">
        <?php foreach (\App\Models\Equipment::OWNERSHIP_TYPES as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($equipment['ownership_type'] ?? 'owned') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label><?= t('common.status') ?></label>
      <select name="status">
        <?php foreach (\App\Models\Equipment::STATUSES as $val => $label): ?>
          <option value="<?= $val ?>" <?= ($equipment['status'] ?? 'available') === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.equipment.purchase_date') ?></label><input type="date" name="purchase_date" value="<?= e($equipment['purchase_date'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('user.equipment.purchase_cost') ?></label><input type="number" step="0.01" min="0" name="purchase_cost" value="<?= e((string)($equipment['purchase_cost'] ?? '')) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.equipment.rental_cost_per_day') ?></label><input type="number" step="0.01" min="0" name="rental_cost_per_day" value="<?= e((string)($equipment['rental_cost_per_day'] ?? '')) ?>"></div>
    <div class="form-group">
      <label><?= t('common.supplier') ?></label>
      <select name="supplier_id">
        <option value="">—</option>
        <?php foreach ($suppliers as $s): ?>
          <option value="<?= $s->id ?>" <?= (int)($equipment['supplier_id'] ?? 0) === $s->id ? 'selected' : '' ?>><?= e($s->name) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="form-group"><label><?= t('common.notes') ?></label><textarea name="notes"><?= e($equipment['notes'] ?? '') ?></textarea></div>

  <button type="submit" class="btn btn-primary"><?= $equipment ? t('common.save_changes') : t('user.equipment.add_equipment') ?></button>
</form>

@endsection
