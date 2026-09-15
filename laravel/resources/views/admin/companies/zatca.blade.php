@extends('layouts.admin')

@section('content')
<?php
$statusLabels = [
    'not_started' => ['ZATCA onboarding not started', 'gray'],
    'csr_generated' => ['CSR generated — awaiting compliance CSID', 'yellow'],
    'compliance_pending' => ['Compliance CSID issued — run compliance checks', 'yellow'],
    'compliance_verified' => ['Compliance checks passed — awaiting production CSID', 'yellow'],
    'onboarded' => ['Live — can submit invoices to ZATCA', 'green'],
    'error' => ['ZATCA request failed', 'red'],
    'failed' => ['ZATCA request failed', 'red'],
];
$status = $company['zatca_status'] ?: 'not_started';
[$statusLabel, $statusColor] = $statusLabels[$status] ?? [$status, 'gray'];
$envLabels = ['developer' => 'Developer (sandbox)', 'simulation' => 'Simulation', 'production' => 'Production (live)'];
?>
<div class="page-head">
  <div>
    <h1><?= t('admin.zatca.title') ?> — <?= e($company['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Onboard this company for ZATCA Phase 1 (QR code, already active on every invoice) and Phase 2 (Fatoora integration, XAdES-signed XML clearance/reporting).</p>
  </div>
  <a href="/admin/companies/<?= $company['id'] ?>" class="btn btn-secondary">← <?= t('admin.zatca.back_to_company') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.onboarding_status') ?></div>
    <div class="value" style="font-size:16px;"><span class="badge badge-<?= $statusColor ?>"><?= e($statusLabel) ?></span></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.environment') ?></div>
    <div class="value" style="font-size:16px;"><?= e($envLabels[$company['zatca_environment']] ?? ucfirst((string) $company['zatca_environment'])) ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.company.vat_number') ?></div>
    <div class="value" style="font-size:16px;"><?= e($company['vat_number'] ?: '— not set —') ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.last_icv') ?></div>
    <div class="value"><?= (int) ($company['zatca_last_icv'] ?? 0) ?></div>
  </div>
</div>

<?php if (!empty($company['zatca_last_error'])): ?>
  <div class="alert alert-error" style="margin-bottom:24px;"><strong><?= t('admin.zatca.last_error') ?></strong> <?= e($company['zatca_last_error']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step1') ?></h3>
  <p class="help-text">
    <strong>Phase 1 (QR code)</strong> is already fully active — every invoice PDF this company generates includes a compliant ZATCA QR code with seller name, VAT number, timestamp and totals, no setup required.
  </p>
  <p class="help-text">
    <strong>Phase 2 (Fatoora integration)</strong> requires this company to be onboarded with ZATCA directly: a cryptographic certificate is issued to their VAT number, every invoice's UBL XML is XAdES-signed and hash-chained to the previous one, and it is reported to ZATCA's servers. That onboarding needs a one-time OTP generated from <em>this company's own</em> ZATCA Fatoora portal account — it cannot be skipped or simulated. Get the OTP from the company, then complete the steps below.
  </p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/test-connection" style="margin-top:10px;">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-secondary">Test ZATCA gateway connectivity</button>
  </form>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step2') ?></h3>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/settings">
    <?= csrf_field() ?>
    <div class="form-group">
      <label><?= t('admin.zatca.env_select') ?></label>
      <select name="zatca_environment">
        <option value="developer" <?= ($company['zatca_environment'] ?: 'developer') === 'developer' ? 'selected' : '' ?>>Developer (sandbox / developer-portal)</option>
        <option value="simulation" <?= $company['zatca_environment'] === 'simulation' ? 'selected' : '' ?>>Simulation</option>
        <option value="production" <?= $company['zatca_environment'] === 'production' ? 'selected' : '' ?>>Production (live)</option>
      </select>
    </div>
    <div style="display:flex;gap:20px;margin:10px 0;">
      <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="zatca_sync_b2b" value="1" <?= !empty($company['zatca_sync_b2b']) ? 'checked' : '' ?>> Standard (B2B) invoices</label>
      <label style="display:flex;align-items:center;gap:6px;"><input type="checkbox" name="zatca_sync_b2c" value="1" <?= !empty($company['zatca_sync_b2c']) ? 'checked' : '' ?>> Simplified (B2C) invoices</label>
    </div>
    <div class="form-group">
      <label>EGS serial number</label>
      <input type="text" name="zatca_egs_serial" value="<?= e($company['zatca_egs_serial'] ?? '') ?>" placeholder="1-BuildXact|2-1.0.0|3-<?= $company['id'] ?>">
    </div>
    <div class="form-group">
      <label>Common name (CSR)</label>
      <input type="text" name="zatca_common_name" value="<?= e($company['zatca_common_name'] ?? '') ?>" placeholder="<?= e($company['name']) ?>">
    </div>
    <div class="form-group">
      <label>Organization unit name (CSR)</label>
      <input type="text" name="zatca_organization_unit_name" value="<?= e($company['zatca_organization_unit_name'] ?? '') ?>" placeholder="<?= e($company['name']) ?>">
    </div>
    <div class="form-group">
      <label>Business category (CSR)</label>
      <input type="text" name="zatca_business_category" value="<?= e($company['zatca_business_category'] ?? '') ?>" placeholder="Construction / Contracting">
    </div>
    <button type="submit" class="btn btn-secondary"><?= t('common.save') ?></button>
  </form>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step3') ?></h3>
  <p class="help-text">Generates an EC (secp256k1) key pair and a ZATCA-formatted CSR (with the custom certificateTemplateName/subjectAltName extensions ZATCA's onboarding requires) for this company. Requires the company's VAT number to be set first (Company Settings).</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/csr" onsubmit="return confirm('Generating a new CSR will replace any existing one. Continue?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary" <?= empty($company['vat_number']) ? 'disabled' : '' ?>><?= t('admin.zatca.generate_csr') ?></button>
  </form>
  <?php if (!empty($company['zatca_csr'])): ?>
    <div style="margin-top:14px;">
      <label><?= t('admin.zatca.current_csr') ?> (base64)</label>
      <textarea readonly rows="6" style="width:100%;font-family:monospace;font-size:12px;"><?= e($company['zatca_csr']) ?></textarea>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step4') ?></h3>
  <p class="help-text">Enter the OTP the company generated from their own Fatoora portal account (Fatoora → onboard EGS unit) to exchange the CSR for a compliance CSID.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/compliance-csid" style="display:flex;gap:8px;align-items:end;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <label><?= t('admin.zatca.otp') ?></label>
      <input type="text" name="otp" placeholder="6-digit OTP from Fatoora portal" <?= empty($company['zatca_csr']) ? 'disabled' : '' ?>>
    </div>
    <button type="submit" class="btn btn-primary" <?= empty($company['zatca_csr']) ? 'disabled' : '' ?>><?= t('admin.zatca.request_compliance_csid') ?></button>
  </form>
  <?php if (!empty($company['zatca_compliance_csid'])): ?>
    <p class="help-text" style="margin-top:10px;color:var(--success);"><?= t('admin.zatca.compliance_issued') ?></p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3>5. Run compliance checks</h3>
  <p class="help-text">ZATCA requires the EGS to prove it can generate valid sample invoices for every declared profile (Standard/Simplified) before it will issue a production CSID. This submits one signed sample per enabled profile above.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/compliance-check">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary" <?= empty($company['zatca_compliance_csid']) ? 'disabled' : '' ?>>Run compliance checks</button>
  </form>
  <?php if (($company['zatca_status'] ?? null) === 'compliance_verified' || in_array($company['zatca_status'] ?? null, ['onboarded'], true)): ?>
    <p class="help-text" style="margin-top:10px;color:var(--success);">Compliance checks passed.</p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step5') ?></h3>
  <p class="help-text">Once the compliance checks have passed, exchange the compliance CSID for the production CSID. This activates live invoice reporting.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/production-csid">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary" <?= empty($company['zatca_compliance_csid']) ? 'disabled' : '' ?>><?= t('admin.zatca.request_production_csid') ?></button>
  </form>
  <?php if ($status === 'onboarded'): ?>
    <p class="help-text" style="margin-top:10px;color:var(--success);"><?= t('admin.zatca.live_notice') ?></p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3>Reset onboarding</h3>
  <p class="help-text">Clears the CSR, private key, and every CSID/secret for this company so onboarding can be restarted from scratch. Does not affect already-submitted invoices.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/reset" onsubmit="return confirm('This clears the CSR, private key, and all CSIDs for this company. Continue?');">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-secondary">Reset onboarding</button>
  </form>
</div>

@endsection
