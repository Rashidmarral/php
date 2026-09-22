@extends('layouts.admin')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($company['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e($company['email']) ?> · <?= e($company['city']) ?></p>
  </div>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/status" style="display:flex;gap:8px;">
    <?= csrf_field() ?>
    <select name="status">
      <?php foreach (['trial'=>t('admin.status.trial'),'active'=>t('admin.status.active'),'past_due'=>t('admin.status.past_due'),'suspended'=>t('admin.status.suspended'),'cancelled'=>t('admin.status.cancelled')] as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $company['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary"><?= t('admin.company.update_status') ?></button>
  </form>
</div>

<div style="margin-bottom:24px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="/admin/companies/<?= $company['id'] ?>/zatca" class="btn btn-secondary"><?= t('admin.company.zatca_link') ?> →</a>
  <?php if (auth()->user()->isSuperAdmin()): ?>
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/impersonate" style="display:inline;">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-light">🕵️ <?= t('admin.company.login_as_owner') ?></button>
    </form>
  <?php endif; ?>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('common.status') ?></div><div class="value" style="font-size:16px;"><span class="badge badge-<?= $company['status']==='active'?'green':($company['status']==='suspended'?'red':'yellow') ?>"><?= e($company['status']) ?></span></div></div>
  <div class="kpi"><div class="label"><?= t('admin.company.team_members') ?></div><div class="value"><?= count($users) ?></div></div>
  <div class="kpi"><div class="label"><?= t('side.projects') ?></div><div class="value"><?= $projectCount ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.company.trial_ends') ?></div><div class="value" style="font-size:16px;"><?= e($company['trial_ends_at'] ?: '—') ?></div></div>
</div>

<details class="card" style="margin-bottom:24px;max-width:720px;">
  <summary style="cursor:pointer;font-weight:700;">📝 <?= t('admin.company.edit_profile') ?></summary>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/profile" enctype="multipart/form-data" style="margin-top:16px;">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.name_en') ?></label><input type="text" name="name" value="<?= e($company['name']) ?>"></div>
      <div class="form-group"><label><?= t('admin.company.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($company['name_ar'] ?? '') ?>" placeholder="اسم الشركة"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" value="<?= e($company['email']) ?>"></div>
      <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" value="<?= e($company['phone']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.city') ?></label><input type="text" name="city" value="<?= e($company['city']) ?>"></div>
      <div class="form-group"><label><?= t('admin.company.address_freetext') ?></label><input type="text" name="address" value="<?= e($company['address'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('auth.team_size') ?></label>
        <select name="team_size">
          <option value="">—</option>
          <?php foreach (['1-5', '6-15', '16-50', '51-200', '200+'] as $ts): ?>
            <option value="<?= $ts ?>" <?= ($company['team_size'] ?? '') === $ts ? 'selected' : '' ?>><?= $ts ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.cr_number') ?></label><input type="text" name="cr_number" value="<?= e($company['cr_number']) ?>"></div>
      <div class="form-group"><label><?= t('admin.company.vat_number') ?></label><input type="text" name="vat_number" value="<?= e($company['vat_number']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.mol_establishment_number') ?></label><input type="text" name="mol_establishment_number" value="<?= e($company['mol_establishment_number'] ?? '') ?>"></div>
      <div></div>
    </div>

    <h3 style="font-size:13px;margin-top:16px;"><?= t('admin.company.classification') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.company.classification_grade') ?></label>
        <select name="contractor_classification">
          <option value=""><?= t('admin.company.not_classified') ?></option>
          <?php foreach (['1'=>t('admin.company.grade').' 1','2'=>t('admin.company.grade').' 2','3'=>t('admin.company.grade').' 3','4'=>t('admin.company.grade').' 4','5'=>t('admin.company.grade').' 5'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($company['contractor_classification'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('admin.company.classification_number') ?></label><input type="text" name="contractor_classification_number" value="<?= e($company['contractor_classification_number'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:13px;margin-top:16px;"><?= t('admin.company.zatca_address') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.building_number') ?></label><input type="text" name="building_number" maxlength="4" value="<?= e($company['building_number'] ?? '') ?>" placeholder="1234"></div>
      <div class="form-group"><label><?= t('admin.company.street_name') ?></label><input type="text" name="street_name" value="<?= e($company['street_name'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.district') ?></label><input type="text" name="district" value="<?= e($company['district'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('admin.company.postal_code') ?></label><input type="text" name="postal_code" maxlength="5" value="<?= e($company['postal_code'] ?? '') ?>" placeholder="12345"></div>
    </div>
    <div class="form-group" style="max-width:240px;"><label><?= t('admin.company.additional_number') ?></label><input type="text" name="additional_number" maxlength="4" value="<?= e($company['additional_number'] ?? '') ?>" placeholder="6789"></div>

    <h3 style="font-size:13px;margin-top:16px;"><?= t('admin.company.legal_documents') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.company.cr_certificate') ?></label>
        <?php if (!empty($company['cr_document_path'])): ?>
          <p class="help-text"><a href="<?= e($company['cr_document_path']) ?>" target="_blank" rel="noopener"><?= t('admin.company.view_uploaded_file') ?> →</a></p>
        <?php endif; ?>
        <input type="file" name="cr_document" accept="application/pdf,image/png,image/jpeg">
      </div>
      <div class="form-group">
        <label><?= t('admin.company.vat_certificate') ?></label>
        <?php if (!empty($company['vat_document_path'])): ?>
          <p class="help-text"><a href="<?= e($company['vat_document_path']) ?>" target="_blank" rel="noopener"><?= t('admin.company.view_uploaded_file') ?> →</a></p>
        <?php endif; ?>
        <input type="file" name="vat_document" accept="application/pdf,image/png,image/jpeg">
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:8px;"><?= t('admin.company.save_profile') ?></button>
  </form>
</details>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.company.change_plan') ?></h3>
  <p class="help-text"><?= t('admin.company.change_plan_hint') ?></p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/plan" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="plan_id">
        <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($company['plan_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <select name="billing_cycle">
        <option value="monthly"><?= t('billing.monthly') ?></option>
        <option value="yearly"><?= t('billing.yearly') ?></option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('admin.company.apply') ?></button>
  </form>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('admin.company.team_members') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.email') ?></th><th><?= t('common.role') ?></th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= e($u['name']) ?></td><td><?= e($u['email']) ?></td><td><span class="badge badge-blue"><?= e(ucfirst($u['role'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3><?= t('admin.company.subscription_history') ?></h3>
    <table class="data">
      <thead><tr><th><?= t('common.plan') ?></th><th><?= t('admin.company.cycle') ?></th><th><?= t('common.status') ?></th></tr></thead>
      <tbody>
      <?php foreach ($subscriptions as $s): ?>
        <tr><td><?= e($s['plan_name']) ?></td><td><?= e($s['billing_cycle']) ?></td><td><span class="badge badge-gray"><?= e($s['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('aside.payments') ?></h3>
  <?php if (empty($payments)): ?>
    <p class="help-text"><?= t('admin.company.no_payments') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('common.reference') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr><td><?= e($p['created_at']) ?></td><td><?= e($p['reference']) ?></td><td><?= money((float)$p['amount']) ?></td><td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':'red') ?>"><?= e($p['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (auth()->user()->isSuperAdmin()): ?>
<details class="card" style="margin-top:24px;max-width:520px;border-color:#e0958c;">
  <summary style="cursor:pointer;font-weight:700;color:#a6362b;">⚠️ <?= t('admin.company.danger_zone') ?></summary>
  <div style="margin-top:16px;">
    <h3 style="font-size:14px;"><?= t('admin.company.delete_permanently') ?></h3>
    <p class="help-text"><?= t('admin.company.delete_warning') ?></p>
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/hard-delete" onsubmit="return confirm('This permanently deletes all of ' + <?= json_encode($company['name']) ?> + '\'s data. This cannot be undone. Continue?');">
      <?= csrf_field() ?>
      <div class="form-group">
        <label><?= t('admin.company.confirm_name_prompt') ?> (<strong><?= e($company['name']) ?></strong>)</label>
        <input type="text" name="confirm_name" required>
      </div>
      <button type="submit" class="btn btn-danger"><?= t('admin.company.delete_permanently') ?></button>
    </form>
  </div>
</details>
<?php endif; ?>

@endsection
