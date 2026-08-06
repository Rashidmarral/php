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
      <h3>💳 Payment Gateway</h3>
      <span class="badge badge-<?= $paymentGateway !== 'manual' ? 'green' : 'yellow' ?>"><?= $paymentGateway !== 'manual' ? ucfirst($paymentGateway) : 'Manual (demo mode)' ?></span>
    </div>
    <p class="help-text">
      Subscription payments are currently simulated. Wire up
      <a href="https://moyasar.com" target="_blank" rel="noopener">Moyasar</a>,
      <a href="https://hyperpay.com" target="_blank" rel="noopener">HyperPay</a>,
      <a href="https://paytabs.com" target="_blank" rel="noopener">PayTabs</a>, or
      <a href="https://tap.company" target="_blank" rel="noopener">Tap</a> for real mada/Visa/Mastercard
      charges — ask your platform administrator.
    </p>
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
      <span class="badge badge-yellow">Not configured</span>
    </div>
    <p class="help-text">Invoices currently use sequential numbering with VAT captured per invoice. Full ZATCA Phase 2 compliance (QR codes, XML/UBL, cryptographic stamps) requires additional setup.</p>
  </div>
</div>
