<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Integrations</h1>
</div>

<div class="grid grid-2">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>📊 Google Sheets Price Sync</h3>
      <span class="badge badge-<?= !empty($company['price_sync_url']) ? 'green' : 'gray' ?>"><?= !empty($company['price_sync_url']) ? 'Connected' : 'Not connected' ?></span>
    </div>
    <p class="help-text">
      Publish a Google Sheet to the web as CSV (File → Share → Publish to web → CSV), then paste
      the link here. Columns: <code>sku, name, category, unit, unit_cost</code>. Sync it anytime
      from the <a href="/app/materials">Materials & Pricing</a> page.
    </p>
    <?php if (Auth::isCompanyOwner()): ?>
      <form method="post" action="/app/integrations/google-sheets" style="display:flex;gap:8px;">
        <?= Csrf::field() ?>
        <input type="url" name="price_sync_url" placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv" value="<?= View::e($company['price_sync_url'] ?? '') ?>" style="flex:1;">
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
      </form>
    <?php endif; ?>
    <?php if (!empty($company['price_sync_last_at'])): ?>
      <p class="help-text" style="margin-top:8px;">Last synced: <?= View::e($company['price_sync_last_at']) ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>💳 Subscription Payment Method</h3>
      <span class="badge badge-<?= $moyasarConfigured ? 'green' : 'yellow' ?>"><?= $moyasarConfigured ? 'Moyasar connected' : 'Bank transfer only' ?></span>
    </div>
    <p class="help-text">
      Your own BuildXact Saudi subscription can be paid by bank transfer (reviewed and approved
      by your platform administrator) at any time from <a href="/app/billing">Billing</a>.
      <?= $moyasarConfigured ? 'Card / mada / Apple Pay / STC Pay checkout via Moyasar is also enabled.' : 'Online card checkout isn\'t enabled yet — ask your platform administrator to connect Moyasar.' ?>
    </p>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>🧾 Accept Client Payments</h3>
      <span class="badge badge-<?= $clientMoyasarConfigured ? 'green' : 'gray' ?>"><?= $clientMoyasarConfigured ? 'Enabled' : 'Not connected' ?></span>
    </div>
    <p class="help-text">
      Connect your <strong>own</strong> Moyasar account so a "Pay now" button appears on every
      invoice you send — payments go directly into your Moyasar account, not through BuildXact
      Saudi. Get your keys from your <a href="https://dashboard.moyasar.com" target="_blank" rel="noopener">Moyasar dashboard</a>.
    </p>
    <?php if (Auth::can('manage_company_settings')): ?>
      <form method="post" action="/app/integrations/client-payments">
        <?= Csrf::field() ?>
        <div class="form-group">
          <label><input type="checkbox" name="moyasar_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($company['moyasar_enabled']) ? 'checked' : '' ?>> Enabled</label>
        </div>
        <div class="form-group"><label>Publishable key</label><input type="text" name="moyasar_publishable_key" value="<?= View::e($company['moyasar_publishable_key'] ?? '') ?>" placeholder="pk_live_..."></div>
        <div class="form-group">
          <label>Secret key</label>
          <div class="password-field">
            <input type="password" name="moyasar_secret_key" placeholder="<?= !empty($company['moyasar_secret_key']) ? '••••••••••••••••  (leave blank to keep current)' : 'sk_live_...' ?>">
            <?= View::passwordToggle() ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Save</button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>✉️ Email Notifications</h3>
      <span class="badge badge-yellow">Not configured</span>
    </div>
    <p class="help-text">Send invoice, estimate, and team-invite emails automatically once an SMTP provider is connected by your platform administrator.</p>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>🧾 ZATCA E-Invoicing</h3>
      <span class="badge badge-<?= ($company['zatca_status'] ?? 'not_started') === 'active' ? 'green' : 'yellow' ?>">
        <?= ($company['zatca_status'] ?? 'not_started') === 'active' ? 'Phase 2 live' : 'Phase 1 only' ?>
      </span>
    </div>
    <p class="help-text">
      Every invoice already carries a compliant ZATCA Phase 1 QR code automatically.
      <?= ($company['zatca_status'] ?? 'not_started') === 'active'
        ? 'Phase 2 (Fatoora reporting) is active — invoices can be submitted directly to ZATCA from the invoice page.'
        : 'Phase 2 (Fatoora reporting) requires onboarding with a one-time code from your ZATCA account — ask your platform administrator to complete it.' ?>
    </p>
  </div>
</div>
