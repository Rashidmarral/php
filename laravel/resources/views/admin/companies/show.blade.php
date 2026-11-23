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
      <div class="form-group"><label><?= t('admin.company.name_en') ?></label><input type="text" name="name" required maxlength="150" value="<?= e($company['name']) ?>"></div>
      <div class="form-group"><label><?= t('admin.company.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" maxlength="255" value="<?= e($company['name_ar'] ?? '') ?>" placeholder="اسم الشركة"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.email') ?></label><input type="email" name="email" required maxlength="150" value="<?= e($company['email']) ?>"></div>
      <div class="form-group"><label><?= t('common.phone') ?></label><input type="tel" name="phone" inputmode="tel" maxlength="30" value="<?= e($company['phone']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('admin.company.city') ?></label>
        <select name="city" class="city-select" data-placeholder="<?= e(t('user.settings.city_search_placeholder')) ?>" data-no-results="<?= e(t('user.settings.city_no_results')) ?>">
          <option value=""><?= t('common.select') ?></option>
          <?php foreach (\App\Support\SaudiCities::withExtra($company['city'] ?? null) as $city): ?>
            <option value="<?= e($city['en']) ?>" data-ar="<?= e($city['ar']) ?>" <?= ($company['city'] ?? '') === $city['en'] ? 'selected' : '' ?>><?= e($city['ar'] !== '' ? $city['en'].' / '.$city['ar'] : $city['en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('admin.company.address_freetext') ?></label><input type="text" name="address" maxlength="255" value="<?= e($company['address'] ?? '') ?>"></div>
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
      <div class="form-group"><label><?= t('admin.company.cr_number') ?></label><input type="text" name="cr_number" inputmode="numeric" pattern="\d{10}" maxlength="10" placeholder="1010XXXXXX" value="<?= e($company['cr_number']) ?>"></div>
      <div class="form-group"><label><?= t('admin.company.vat_number') ?></label><input type="text" name="vat_number" inputmode="numeric" pattern="3\d{13}3" maxlength="15" placeholder="3XXXXXXXXXXXXX3" value="<?= e($company['vat_number']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.mol_establishment_number') ?></label><input type="text" name="mol_establishment_number" maxlength="50" value="<?= e($company['mol_establishment_number'] ?? '') ?>"></div>
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
      <div class="form-group"><label><?= t('admin.company.classification_number') ?></label><input type="text" name="contractor_classification_number" maxlength="50" value="<?= e($company['contractor_classification_number'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:13px;margin-top:16px;"><?= t('admin.company.zatca_address') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.building_number') ?></label><input type="text" name="building_number" inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?= e($company['building_number'] ?? '') ?>" placeholder="1234"></div>
      <div class="form-group"><label><?= t('admin.company.street_name') ?></label><input type="text" name="street_name" maxlength="255" value="<?= e($company['street_name'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('admin.company.district') ?></label><input type="text" name="district" maxlength="255" value="<?= e($company['district'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('admin.company.postal_code') ?></label><input type="text" name="postal_code" inputmode="numeric" pattern="\d{5}" maxlength="5" value="<?= e($company['postal_code'] ?? '') ?>" placeholder="12345"></div>
    </div>
    <div class="form-group" style="max-width:240px;"><label><?= t('admin.company.additional_number') ?></label><input type="text" name="additional_number" inputmode="numeric" pattern="\d{4}" maxlength="4" value="<?= e($company['additional_number'] ?? '') ?>" placeholder="6789"></div>

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
      <select name="plan_id" class="combo-select" data-placeholder="<?= e(t('admin.company.plan_search_placeholder')) ?>" data-no-results="<?= e(t('common.no_results')) ?>">
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

<?php
  // Purely presentational grouping of Feature::ALL for this page only — never changes what a
  // key means or how it's stored, just how it's organized on screen. A key that's ever added
  // to Feature::ALL and forgotten here still renders, in a catch-all "Other" group below.
  $featureCategories = [
    'admin.company.feature_group_estimating' => ['takeoff', 'quick_estimate', 'asphalt_estimate', 'ai_estimate_generator', 'estimate_templates', 'materials'],
    'admin.company.feature_group_financial' => ['recurring_invoices', 'online_invoice_payments', 'zatca_phase2', 'cash_flow_forecasting', 'multi_currency', 'bank_guarantees'],
    'admin.company.feature_group_procurement' => ['suppliers', 'purchase_orders', 'rfq_quotes', 'subcontractors', 'supplier_portal'],
    'admin.company.feature_group_field_ops' => ['equipment_management', 'safety_tracking', 'timesheets', 'project_photos', 'site_logs', 'punch_list', 'rfi', 'submittals', 'change_orders', 'ld_eot_tracking', 'payment_certificates'],
    'admin.company.feature_group_client' => ['client_portal', 'leads'],
    'admin.company.feature_group_compliance' => ['compliance', 'zakat', 'reports', 'activity_log'],
    'admin.company.feature_group_platform' => ['documents', 'integrations', 'approval_workflow', 'priority_support', 'granular_permissions'],
  ];
  $groupedKeys = array_merge(...array_values($featureCategories));
  $ungrouped = array_diff(array_keys(\App\Support\Feature::ALL), $groupedKeys);
  if (!empty($ungrouped)) {
    $featureCategories['admin.company.feature_group_other'] = array_values($ungrouped);
  }
  $overrideCount = count($company['feature_overrides'] ?? []);
?>
<div class="card" style="margin-bottom:24px;max-width:900px;">
  <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:8px;">
    <div>
      <h3 style="margin-bottom:2px;"><?= t('admin.company.feature_overrides') ?></h3>
      <p class="help-text" style="margin-top:0;"><?= t('admin.company.feature_overrides_hint') ?></p>
    </div>
    <span class="badge <?= $overrideCount > 0 ? 'badge-blue' : 'badge-gray' ?>" id="feature-override-count" style="white-space:nowrap;">
      <?= e(t('admin.company.feature_override_count', ['count' => $overrideCount])) ?>
    </span>
  </div>

  <div style="display:flex;gap:10px;align-items:center;margin:14px 0;flex-wrap:wrap;">
    <input type="text" id="feature-search" placeholder="<?= e(t('admin.company.feature_search_placeholder')) ?>" style="flex:1;min-width:220px;max-width:340px;">
    <button type="button" class="btn btn-sm btn-light" id="feature-reset-defaults"><?= t('admin.company.feature_reset_all') ?></button>
  </div>

  <form method="post" action="/admin/companies/<?= $company['id'] ?>/features" id="feature-overrides-form">
    <?= csrf_field() ?>
    <?php foreach ($featureCategories as $groupLabelKey => $keys): ?>
      <div class="feature-group" style="margin-bottom:6px;border:1px solid var(--border);border-radius:8px;overflow:hidden;">
        <div class="feature-group-head" style="padding:9px 14px;background:#f7f9f8;font-weight:600;font-size:12.5px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">
          <span><?= t($groupLabelKey) ?></span>
          <span class="feature-group-arrow" style="font-size:11px;color:var(--muted);">&#9660;</span>
        </div>
        <div class="feature-group-body">
          <?php foreach ($keys as $key):
            if (!array_key_exists($key, \App\Support\Feature::ALL)) continue;
            $label = \App\Support\Feature::ALL[$key];
            $current = $company['feature_overrides'][$key] ?? null;
            $selected = $current === true ? 'allow' : ($current === false ? 'deny' : 'default');
            $inPlan = !empty($planFeatureFlags[$key]);
          ?>
            <div class="feature-row" data-search="<?= e(strtolower($label)) ?>" style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:10px 14px;border-top:1px solid var(--border);flex-wrap:wrap;">
              <div style="display:flex;align-items:center;gap:8px;min-width:220px;">
                <span style="font-size:13px;"><?= e($label) ?></span>
                <span class="badge <?= $inPlan ? 'badge-green' : 'badge-gray' ?>" style="font-size:10.5px;">
                  <?= $inPlan ? t('admin.company.feature_in_plan') : t('admin.company.feature_not_in_plan') ?>
                </span>
              </div>
              <div class="seg-toggle" role="radiogroup" aria-label="<?= e($label) ?>">
                <input type="radio" name="overrides[<?= $key ?>]" value="default" id="f-<?= $key ?>-default" <?= $selected === 'default' ? 'checked' : '' ?>>
                <label for="f-<?= $key ?>-default"><?= t('common.default') ?></label>
                <input type="radio" name="overrides[<?= $key ?>]" value="allow" id="f-<?= $key ?>-allow" <?= $selected === 'allow' ? 'checked' : '' ?>>
                <label for="f-<?= $key ?>-allow" class="seg-allow"><?= t('admin.company.feature_enable') ?></label>
                <input type="radio" name="overrides[<?= $key ?>]" value="deny" id="f-<?= $key ?>-deny" <?= $selected === 'deny' ? 'checked' : '' ?>>
                <label for="f-<?= $key ?>-deny" class="seg-deny"><?= t('admin.company.feature_disable') ?></label>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary" style="margin-top:14px;"><?= t('admin.company.save_feature_overrides') ?></button>
  </form>
</div>

<style>
  .seg-toggle { display:inline-flex; position:relative; border:1px solid var(--border); border-radius:7px; overflow:hidden; }
  /* Visually hidden but still natively clickable via its <label for>, and still reachable by
     keyboard/screen readers — unlike opacity:0, this never overlaps a sibling label's own
     hit-test area, since the input occupies no visual box at all. */
  .seg-toggle input[type="radio"] { position:absolute; width:1px; height:1px; margin:-1px; padding:0; overflow:hidden; clip:rect(0,0,0,0); white-space:nowrap; border:0; }
  .seg-toggle label { padding:5px 12px; font-size:12px; font-weight:600; cursor:pointer; color:var(--muted); background:#fff; user-select:none; border-right:1px solid var(--border); }
  .seg-toggle label:last-of-type { border-right:none; }
  .seg-toggle input[value="default"]:checked + label { background:#eef1f0; color:var(--text); }
  .seg-toggle input[value="allow"]:checked + label.seg-allow { background:#e7f6ee; color:var(--success); }
  .seg-toggle input[value="deny"]:checked + label.seg-deny { background:#fdecea; color:var(--danger); }
  .feature-group.collapsed .feature-group-body { display:none; }
  .feature-group.collapsed .feature-group-arrow { transform:rotate(-90deg); }
</style>
<script>
(function() {
  const search = document.getElementById('feature-search');
  const rows = document.querySelectorAll('.feature-row[data-search]');
  const groups = document.querySelectorAll('.feature-group');

  search.addEventListener('input', () => {
    const q = search.value.toLowerCase();
    rows.forEach(row => { row.style.display = row.dataset.search.includes(q) ? '' : 'none'; });
    groups.forEach(group => {
      const anyVisible = Array.from(group.querySelectorAll('.feature-row')).some(r => r.style.display !== 'none');
      group.style.display = (q === '' || anyVisible) ? '' : 'none';
      group.classList.toggle('collapsed', q !== '' ? false : group.classList.contains('collapsed'));
    });
  });

  groups.forEach(group => {
    group.querySelector('.feature-group-head').addEventListener('click', () => {
      group.classList.toggle('collapsed');
    });
  });

  document.getElementById('feature-reset-defaults').addEventListener('click', () => {
    document.querySelectorAll('#feature-overrides-form input[type="radio"][value="default"]').forEach(r => { r.checked = true; });
  });
})();
</script>

<div class="card" style="margin-bottom:24px;max-width:720px;">
  <h3><?= t('admin.company.custom_domain') ?></h3>
  <p class="help-text"><?= t('admin.company.custom_domain_hint') ?></p>

  <?php if ($domains->isEmpty()): ?>
    <p class="help-text"><?= t('admin.company.custom_domain_none') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('admin.company.domain') ?></th><th><?= t('common.status') ?></th><th><?= t('common.created') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($domains as $d): ?>
        <tr>
          <td><?= e($d->domain) ?></td>
          <td><span class="badge badge-<?= $d->is_active ? 'green' : 'gray' ?>"><?= $d->is_active ? t('admin.company.domain_active') : t('admin.company.domain_inactive') ?></span></td>
          <td class="help-text"><?= e($d->created_at) ?></td>
          <td style="display:flex;gap:6px;">
            <?php if (auth()->user()->isSuperAdmin()): ?>
              <form method="post" action="/admin/companies/<?= $company['id'] ?>/domains/<?= $d->id ?>/toggle"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light"><?= $d->is_active ? t('admin.company.domain_deactivate') : t('admin.company.domain_activate') ?></button>
              </form>
              <form method="post" action="/admin/companies/<?= $company['id'] ?>/domains/<?= $d->id ?>/delete" onsubmit="return confirm('<?= t('admin.company.domain_remove_confirm') ?>');"><?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('common.remove') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <?php if (auth()->user()->isSuperAdmin()): ?>
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/domains" style="display:flex;gap:8px;align-items:end;margin-top:16px;">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;flex:1;">
        <label><?= t('admin.company.add_domain') ?></label>
        <input type="text" name="domain" placeholder="app.clientdomain.com" maxlength="253" required>
      </div>
      <button type="submit" class="btn btn-primary"><?= t('common.add') ?></button>
    </form>
  <?php endif; ?>
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
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/hard-delete" onsubmit="return confirm('<?= t('admin.company.hard_delete_confirm_before') ?> ' + <?= json_encode($company['name']) ?> + '<?= t('admin.company.hard_delete_confirm_after') ?>');">
      <?= csrf_field() ?>
      <div class="form-group">
        <label><?= t('admin.company.confirm_name_prompt') ?> (<strong><?= e($company['name']) ?></strong>)</label>
        <input type="text" name="confirm_name" maxlength="150" required>
      </div>
      <button type="submit" class="btn btn-danger"><?= t('admin.company.delete_permanently') ?></button>
    </form>
  </div>
</details>
<?php endif; ?>

@endsection
