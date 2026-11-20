<?php use App\Core\View; use App\Core\Csrf; ?>
<?php
$statusLabels = [
    'not_started' => ['ZATCA onboarding not started', 'gray'],
    'csr_generated' => ['CSR generated — awaiting compliance CSID', 'yellow'],
    'compliance_csid' => ['Compliance CSID issued — awaiting production CSID', 'yellow'],
    'active' => ['Live — can submit invoices to ZATCA', 'green'],
    'error' => ['ZATCA request failed', 'red'],
];
$status = $company['zatca_status'] ?: 'not_started';
[$statusLabel, $statusColor] = $statusLabels[$status] ?? [$status, 'gray'];
?>
<div class="page-head">
  <div>
    <h1><?= t('admin.zatca.title') ?> — <?= View::e($company['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;">Onboard this company for ZATCA Phase 1 (QR code, already active on every invoice) and Phase 2 (Fatoora integration, XML reporting).</p>
  </div>
  <a href="/admin/companies/<?= $company['id'] ?>" class="btn btn-secondary">← <?= t('admin.zatca.back_to_company') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.onboarding_status') ?></div>
    <div class="value" style="font-size:16px;"><span class="badge badge-<?= $statusColor ?>"><?= View::e($statusLabel) ?></span></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.environment') ?></div>
    <div class="value" style="font-size:16px;"><?= View::e(ucfirst($company['zatca_environment'] ?: 'sandbox')) ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.company.vat_number') ?></div>
    <div class="value" style="font-size:16px;"><?= View::e($company['vat_number'] ?: '— not set —') ?></div>
  </div>
  <div class="kpi">
    <div class="label"><?= t('admin.zatca.last_icv') ?></div>
    <div class="value"><?= (int) ($company['zatca_last_icv'] ?? 0) ?></div>
  </div>
</div>

<?php if (!empty($company['zatca_last_error'])): ?>
  <div class="alert alert-error" style="margin-bottom:24px;"><strong><?= t('admin.zatca.last_error') ?></strong> <?= View::e($company['zatca_last_error']) ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.zatca.step1') ?></h3>
  <p class="help-text">
    <strong>Phase 1 (QR code)</strong> is already fully active — every quote/invoice PDF this company generates includes a compliant ZATCA QR code with seller name, VAT number, timestamp and totals, no setup required.
  </p>
  <p class="help-text">
    <strong>Phase 2 (Fatoora integration)</strong> requires this company to be onboarded with ZATCA directly: a cryptographic certificate is issued to their VAT number, and every invoice is reported to ZATCA's servers with a tamper-evident hash chain. That onboarding needs a one-time OTP generated from <em>this company's own</em> ZATCA Fatoora portal account — it cannot be skipped or simulated. Get the OTP from the company, then complete the steps below.
  </p>
</div>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.zatca.step2') ?></h3>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/environment" style="display:flex;gap:8px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <label><?= t('admin.zatca.env_select') ?></label>
      <select name="zatca_environment">
        <option value="sandbox" <?= ($company['zatca_environment'] ?: 'sandbox') === 'sandbox' ? 'selected' : '' ?>><?= t('admin.zatca.env_sandbox') ?></option>
        <option value="production" <?= $company['zatca_environment'] === 'production' ? 'selected' : '' ?>><?= t('admin.zatca.env_production') ?></option>
      </select>
    </div>
    <button type="submit" class="btn btn-secondary"><?= t('common.save') ?></button>
  </form>
</div>

<div class="card" style="margin-bottom:24px;max-width:640px;">
  <h3><?= t('admin.zatca.step3') ?></h3>
  <p class="help-text">Generates an EC key pair and CSR for this company. Requires the company's VAT number to be set first (Company Settings).</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/csr" onsubmit="return confirm('Generating a new CSR will replace any existing one. Continue?');">
    <?= Csrf::field() ?>
    <button type="submit" class="btn btn-primary" <?= empty($company['vat_number']) ? 'disabled' : '' ?>><?= t('admin.zatca.generate_csr') ?></button>
  </form>
  <?php if (!empty($company['zatca_csr'])): ?>
    <div style="margin-top:14px;">
      <label><?= t('admin.zatca.current_csr') ?></label>
      <textarea readonly rows="6" style="width:100%;font-family:monospace;font-size:12px;"><?= View::e($company['zatca_csr']) ?></textarea>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.zatca.step4') ?></h3>
  <p class="help-text">Enter the OTP the company generated from their own Fatoora portal account (Fatoora → onboard EGS unit) to exchange the CSR for a compliance CSID.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/compliance-csid" style="display:flex;gap:8px;align-items:end;">
    <?= Csrf::field() ?>
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

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3><?= t('admin.zatca.step5') ?></h3>
  <p class="help-text">Once the compliance CSID has passed ZATCA's compliance checks, exchange it for the production CSID. This activates live invoice reporting.</p>
  <form method="post" action="/admin/companies/<?= $company['id'] ?>/zatca/production-csid" style="display:flex;gap:8px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <label><?= t('admin.zatca.compliance_request_id') ?></label>
      <input type="text" name="compliance_request_id" placeholder="From ZATCA compliance response" <?= empty($company['zatca_compliance_csid']) ? 'disabled' : '' ?>>
    </div>
    <button type="submit" class="btn btn-primary" <?= empty($company['zatca_compliance_csid']) ? 'disabled' : '' ?>><?= t('admin.zatca.request_production_csid') ?></button>
  </form>
  <?php if ($status === 'active'): ?>
    <p class="help-text" style="margin-top:10px;color:var(--success);"><?= t('admin.zatca.live_notice') ?></p>
  <?php endif; ?>
</div>
