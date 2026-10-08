<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= t('admin.integrations.title') ?></h1>
</div>
<p class="help-text" style="max-width:820px;margin-bottom:20px;">
  Everything the platform can connect to, in one place — status, where to configure it, and where
  to get the credentials it needs. Each integration is real, working code: it just needs your
  actual account details from the provider before it can send/receive anything.
</p>

<div class="grid grid-2">

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>💳 Moyasar (online payments)</h3>
      <span class="badge badge-<?= $moyasarConfigured ? 'green' : 'gray' ?>"><?= $moyasarConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text">Card, mada, Apple Pay, and STC Pay checkout for subscription payments.</p>
    <p class="help-text"><strong>Where to get it:</strong> create an account at <a href="https://moyasar.com" target="_blank" rel="noopener">moyasar.com</a>, complete their merchant verification, then copy your Publishable Key and Secret Key from Dashboard → Developers → API Keys.</p>
    <a href="/admin/settings/payments" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>🏦 Bank transfer</h3>
      <span class="badge badge-<?= $bankTransferEnabled ? 'green' : 'gray' ?>"><?= $bankTransferEnabled ? t('common.enabled') : t('common.disabled') ?></span>
    </div>
    <p class="help-text">Manual offline payments — a company submits a transfer reference, you approve it. No third-party account needed, just your own bank details.</p>
    <a href="/admin/settings/payments" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>💬 WhatsApp Business API</h3>
      <span class="badge badge-<?= $whatsappConfigured ? 'green' : 'gray' ?>"><?= $whatsappConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text">Fully automated WhatsApp notifications (e.g. "your invoice is ready"). The zero-setup "Send via WhatsApp" button on every invoice/estimate works regardless of this.</p>
    <p class="help-text"><strong>Where to get it:</strong> <a href="https://developers.facebook.com/docs/whatsapp/cloud-api/get-started" target="_blank" rel="noopener">Meta for Developers</a> → create a Business App → add the WhatsApp product → register a phone number → copy the Phone Number ID and a permanent Access Token.</p>
    <a href="/admin/settings/notifications" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>✉️ SMTP email</h3>
      <span class="badge badge-<?= $smtpConfigured ? 'green' : 'gray' ?>"><?= $smtpConfigured ? t('admin.integrations.connected') : t('admin.integrations.not_connected') ?></span>
    </div>
    <p class="help-text">Team-invite emails, with more transactional email planned. Works with any real mailbox or provider.</p>
    <p class="help-text"><strong>Where to get it:</strong> your own Google Workspace/Microsoft 365 mailbox settings, or a provider dashboard (SendGrid, Mailgun, Brevo) under "SMTP settings."</p>
    <a href="/admin/settings/email" class="btn btn-sm btn-outline"><?= t('admin.integrations.configure') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>🧾 ZATCA e-invoicing</h3>
      <span class="badge badge-blue"><?= t('admin.integrations.per_company') ?></span>
    </div>
    <p class="help-text">Phase 1 QR codes are automatic for every company. Phase 2 (Fatoora reporting) is onboarded per company since it requires that specific company's own ZATCA account and OTP.</p>
    <p class="help-text"><strong>Where to get it:</strong> the company's own <a href="https://fatoora.zatca.gov.sa" target="_blank" rel="noopener">ZATCA Fatoora portal</a> account — ask them for the OTP when you're ready to onboard them.</p>
    <a href="/admin/companies" class="btn btn-sm btn-outline"><?= t('admin.integrations.go_to_companies') ?></a>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3>📊 Google Sheets price sync</h3>
      <span class="badge badge-blue"><?= t('admin.integrations.per_company') ?></span>
    </div>
    <p class="help-text">Each company links their own published Google Sheet for material pricing — configured from their Integrations page, not centrally.</p>
    <p class="help-text"><strong>Where to get it:</strong> nothing to obtain — a company publishes their own sheet to the web as CSV (File → Share → Publish to web) and pastes the link themselves.</p>
  </div>

</div>
