<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($company['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= View::e($company['email']) ?> · <?= View::e($company['city']) ?></p>
  </div>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/status" style="display:flex;gap:8px;">
    <?= Csrf::field() ?>
    <select name="status">
      <?php foreach (['trial'=>'Trial','active'=>'Active','past_due'=>'Past due','suspended'=>'Suspended','cancelled'=>'Cancelled'] as $val=>$label): ?>
        <option value="<?= $val ?>" <?= $company['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary">Update status</button>
  </form>
</div>

<div style="margin-bottom:24px;display:flex;gap:8px;flex-wrap:wrap;">
  <a href="/admin/companies/<?= $company['id'] ?>/zatca" class="btn btn-secondary">ZATCA e-invoicing compliance →</a>
  <?php if (Auth::isSuperAdmin()): ?>
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/impersonate" style="display:inline;">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-light">🕵️ Log in as owner</button>
    </form>
  <?php endif; ?>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-<?= $company['status']==='active'?'green':($company['status']==='suspended'?'red':'yellow') ?>"><?= View::e($company['status']) ?></span></div></div>
  <div class="kpi"><div class="label">Team members</div><div class="value"><?= count($users) ?></div></div>
  <div class="kpi"><div class="label">Projects</div><div class="value"><?= $projectCount ?></div></div>
  <div class="kpi"><div class="label">Trial ends</div><div class="value" style="font-size:16px;"><?= View::e($company['trial_ends_at'] ?: '—') ?></div></div>
</div>

<details class="card" style="margin-bottom:24px;max-width:720px;">
  <summary style="cursor:pointer;font-weight:700;">📝 Edit company profile (on behalf of this company)</summary>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/profile" enctype="multipart/form-data" style="margin-top:16px;">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group"><label>Company name (English)</label><input type="text" name="name" value="<?= View::e($company['name']) ?>"></div>
      <div class="form-group"><label>Company name (Arabic)</label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($company['name_ar'] ?? '') ?>" placeholder="اسم الشركة"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= View::e($company['email']) ?>"></div>
      <div class="form-group"><label>Phone</label><input type="tel" name="phone" value="<?= View::e($company['phone']) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>City</label><input type="text" name="city" value="<?= View::e($company['city']) ?>"></div>
      <div class="form-group"><label>Address (free text)</label><input type="text" name="address" value="<?= View::e($company['address'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>CR number</label><input type="text" name="cr_number" value="<?= View::e($company['cr_number']) ?>"></div>
      <div class="form-group"><label>VAT number</label><input type="text" name="vat_number" value="<?= View::e($company['vat_number']) ?>"></div>
    </div>

    <h3 style="font-size:13px;margin-top:16px;">Contractor classification</h3>
    <div class="form-row">
      <div class="form-group">
        <label>Classification grade</label>
        <select name="contractor_classification">
          <option value="">Not classified</option>
          <?php foreach (['1'=>'Grade 1','2'=>'Grade 2','3'=>'Grade 3','4'=>'Grade 4','5'=>'Grade 5'] as $val => $label): ?>
            <option value="<?= $val ?>" <?= ($company['contractor_classification'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Classification/license number</label><input type="text" name="contractor_classification_number" value="<?= View::e($company['contractor_classification_number'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:13px;margin-top:16px;">ZATCA-compliant address</h3>
    <div class="form-row">
      <div class="form-group"><label>Building number</label><input type="text" name="building_number" maxlength="4" value="<?= View::e($company['building_number'] ?? '') ?>" placeholder="1234"></div>
      <div class="form-group"><label>Street name</label><input type="text" name="street_name" value="<?= View::e($company['street_name'] ?? '') ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>District</label><input type="text" name="district" value="<?= View::e($company['district'] ?? '') ?>"></div>
      <div class="form-group"><label>Postal code</label><input type="text" name="postal_code" maxlength="5" value="<?= View::e($company['postal_code'] ?? '') ?>" placeholder="12345"></div>
    </div>
    <div class="form-group" style="max-width:240px;"><label>Additional number</label><input type="text" name="additional_number" maxlength="4" value="<?= View::e($company['additional_number'] ?? '') ?>" placeholder="6789"></div>

    <h3 style="font-size:13px;margin-top:16px;">Legal documents</h3>
    <div class="form-row">
      <div class="form-group">
        <label>CR certificate</label>
        <?php if (!empty($company['cr_document_path'])): ?>
          <p class="help-text"><a href="<?= View::e($company['cr_document_path']) ?>" target="_blank" rel="noopener">View uploaded file →</a></p>
        <?php endif; ?>
        <input type="file" name="cr_document" accept="application/pdf,image/png,image/jpeg">
      </div>
      <div class="form-group">
        <label>VAT certificate</label>
        <?php if (!empty($company['vat_document_path'])): ?>
          <p class="help-text"><a href="<?= View::e($company['vat_document_path']) ?>" target="_blank" rel="noopener">View uploaded file →</a></p>
        <?php endif; ?>
        <input type="file" name="vat_document" accept="application/pdf,image/png,image/jpeg">
      </div>
    </div>

    <button type="submit" class="btn btn-primary" style="margin-top:8px;">Save company profile</button>
  </form>
</details>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3>Change plan (admin override)</h3>
  <p class="help-text">Directly assign a plan to this company — no payment record is created, effective immediately.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/plan" style="display:flex;gap:8px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <select name="plan_id">
        <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($company['plan_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <select name="billing_cycle">
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
  </form>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3>Team members</h3>
    <table class="data">
      <thead><tr><th>Name</th><th>Email</th><th>Role</th></tr></thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr><td><?= View::e($u['name']) ?></td><td><?= View::e($u['email']) ?></td><td><span class="badge badge-blue"><?= View::e(ucfirst($u['role'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card">
    <h3>Subscription history</h3>
    <table class="data">
      <thead><tr><th>Plan</th><th>Cycle</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($subscriptions as $s): ?>
        <tr><td><?= View::e($s['plan_name']) ?></td><td><?= View::e($s['billing_cycle']) ?></td><td><span class="badge badge-gray"><?= View::e($s['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3>Payments</h3>
  <?php if (empty($payments)): ?>
    <p class="help-text">No payments recorded.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr><td><?= View::e($p['created_at']) ?></td><td><?= View::e($p['reference']) ?></td><td><?= View::money((float)$p['amount']) ?></td><td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':'red') ?>"><?= View::e($p['status']) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if (Auth::isSuperAdmin()): ?>
<details class="card" style="margin-top:24px;max-width:520px;border-color:#e0958c;">
  <summary style="cursor:pointer;font-weight:700;color:#a6362b;">⚠️ Danger zone</summary>
  <div style="margin-top:16px;">
    <h3 style="font-size:14px;">Permanently delete this company</h3>
    <p class="help-text">Deletes <?= View::e($company['name']) ?> and every project, estimate, invoice, client, and payment it owns. This cannot be undone — use it only for a genuine data-deletion request, not to close an account (use status = Cancelled for that instead).</p>
    <form method="post" action="/admin/companies/<?= $company['id'] ?>/hard-delete" onsubmit="return confirm('This permanently deletes all of ' + <?= json_encode($company['name']) ?> + '\'s data. This cannot be undone. Continue?');">
      <?= Csrf::field() ?>
      <div class="form-group">
        <label>Type the company name (<strong><?= View::e($company['name']) ?></strong>) to confirm</label>
        <input type="text" name="confirm_name" required>
      </div>
      <button type="submit" class="btn btn-danger">Permanently delete company</button>
    </form>
  </div>
</details>
<?php endif; ?>
