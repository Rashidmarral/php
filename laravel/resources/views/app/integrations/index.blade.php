@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.integrations.title') ?></h1>
</div>

<div class="grid grid-2">
  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('user.integrations.sheets_title') ?></h3>
      <span class="badge badge-<?= !empty($company['price_sync_url']) ? 'green' : 'gray' ?>"><?= !empty($company['price_sync_url']) ? t('user.integrations.connected') : t('user.integrations.not_connected') ?></span>
    </div>
    <p class="help-text">
      Publish a Google Sheet to the web as CSV (File → Share → Publish to web → CSV), then paste
      the link here. Columns: <code>sku, name, category, unit, unit_cost</code>. Sync it anytime
      from the <a href="/app/materials"><?= t('side.materials') ?></a> page.
    </p>
    <?php if (auth()->user()->isCompanyOwner()): ?>
      <form method="post" action="/app/integrations/google-sheets" style="display:flex;gap:8px;">
        <?= csrf_field() ?>
        <input type="url" name="price_sync_url" placeholder="https://docs.google.com/spreadsheets/d/.../pub?output=csv" value="<?= e($company['price_sync_url'] ?? '') ?>" style="flex:1;">
        <button type="submit" class="btn btn-primary btn-sm"><?= t('common.save') ?></button>
      </form>
    <?php endif; ?>
    <?php if (!empty($company['price_sync_last_at'])): ?>
      <p class="help-text" style="margin-top:8px;"><?= t('user.integrations.last_synced') ?> <?= e($company['price_sync_last_at']) ?></p>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('user.integrations.subscription_payment') ?></h3>
      <span class="badge badge-<?= $moyasarConfigured ? 'green' : 'yellow' ?>"><?= $moyasarConfigured ? t('user.integrations.moyasar_connected') : t('user.integrations.bank_transfer_only') ?></span>
    </div>
    <p class="help-text">
      Your own <?= e(\App\Models\Setting::siteName()) ?> subscription can be paid by bank transfer (reviewed and approved
      by your platform administrator) at any time from <a href="/app/billing"><?= t('side.billing') ?></a>.
      <?= $moyasarConfigured ? 'Card / mada / Apple Pay / STC Pay checkout via Moyasar is also enabled.' : 'Online card checkout isn\'t enabled yet — ask your platform administrator to connect Moyasar.' ?>
    </p>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('user.integrations.client_payments_title') ?></h3>
      <span class="badge badge-<?= $clientMoyasarConfigured ? 'green' : 'gray' ?>"><?= $clientMoyasarConfigured ? t('common.enabled') : t('user.integrations.not_connected') ?></span>
    </div>
    <p class="help-text">
      Connect your <strong>own</strong> Moyasar account so a "Pay now" button appears on every
      invoice you send — payments go directly into your Moyasar account, not through <?= e(\App\Models\Setting::siteName()) ?>. Get your keys from your <a href="https://dashboard.moyasar.com" target="_blank" rel="noopener">Moyasar dashboard</a>.
    </p>
    <?php if (auth()->user()->can('manage_company_settings')): ?>
      <form method="post" action="/app/integrations/client-payments">
        <?= csrf_field() ?>
        <div class="form-group">
          <label><input type="checkbox" name="moyasar_enabled" value="1" style="width:auto;display:inline-block;" <?= !empty($company['moyasar_enabled']) ? 'checked' : '' ?>> <?= t('user.integrations.enabled') ?></label>
        </div>
        <div class="form-group"><label><?= t('user.integrations.publishable_key') ?></label><input type="text" name="moyasar_publishable_key" value="<?= e($company['moyasar_publishable_key'] ?? '') ?>" placeholder="pk_live_..."></div>
        <div class="form-group">
          <label><?= t('user.integrations.secret_key') ?></label>
          <div class="password-field">
            <input type="password" name="moyasar_secret_key" placeholder="<?= !empty($company['moyasar_secret_key']) ? '••••••••••••••••  (leave blank to keep current)' : 'sk_live_...' ?>">
            <?= passwordToggle() ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm"><?= t('common.save') ?></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('user.integrations.email_notifications') ?></h3>
      <span class="badge badge-yellow"><?= t('user.integrations.not_configured') ?></span>
    </div>
    <p class="help-text">Send invoice, estimate, and team-invite emails automatically once an SMTP provider is connected by your platform administrator.</p>
  </div>

  <div class="card">
    <div style="display:flex;justify-content:space-between;align-items:start;">
      <h3><?= t('user.integrations.zatca_title') ?></h3>
      <span class="badge badge-<?= ($company['zatca_status'] ?? 'not_started') === 'onboarded' ? 'green' : 'yellow' ?>">
        <?= ($company['zatca_status'] ?? 'not_started') === 'onboarded' ? t('user.integrations.phase2_live') : t('user.integrations.phase1_only') ?>
      </span>
    </div>
    <p class="help-text">
      Every invoice already carries a compliant ZATCA Phase 1 QR code automatically.
      <?= ($company['zatca_status'] ?? 'not_started') === 'onboarded'
        ? 'Phase 2 (Fatoora reporting) is active — invoices can be submitted directly to ZATCA from the invoice page.'
        : 'Phase 2 (Fatoora reporting) requires onboarding with a one-time code from your ZATCA account — ask your platform administrator to complete it.' ?>
    </p>
  </div>

  <div class="card">
    <h3>API access</h3>
    <p class="help-text">Token-based REST API for the future mobile app and third-party integrations. Base URL: <code>/api/v1</code>. Send <code>Authorization: Bearer &lt;token&gt;</code> on every request.</p>

    <?php if (!empty($apiTokens)): ?>
      <table class="data" style="margin:14px 0;">
        <thead><tr><th>Name</th><th>Last used</th><th>Created</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($apiTokens as $t): ?>
          <tr>
            <td><?= e($t->name) ?></td>
            <td class="help-text"><?= $t->last_used_at ? e($t->last_used_at->diffForHumans()) : 'Never' ?></td>
            <td class="help-text"><?= e($t->created_at->format('Y-m-d')) ?></td>
            <td>
              <form method="post" action="/app/integrations/api-tokens/<?= $t->id ?>/delete" onsubmit="return confirm('Revoke this API token? Anything using it will stop working immediately.');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">Revoke</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" action="/app/integrations/api-tokens" style="display:flex;gap:8px;align-items:end;">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;flex:1;">
        <label>Token name</label>
        <input type="text" name="name" placeholder="e.g. Mobile app — my iPhone" required>
      </div>
      <button type="submit" class="btn btn-outline">Create token</button>
    </form>
  </div>

  <div class="card">
    <h3>Webhooks</h3>
    <p class="help-text">Get an HTTP POST notification when something happens — new estimate, signed estimate, new invoice, paid invoice. Each request is signed with the webhook's secret (HMAC-SHA256, header <code>X-Webhook-Signature</code>) so you can verify it came from us.</p>

    <?php if (!empty($webhooks)): ?>
      <table class="data" style="margin:14px 0;">
        <thead><tr><th>URL</th><th>Events</th><th>Status</th><th>Last delivery</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($webhooks as $wh): ?>
          <tr>
            <td style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e($wh->url) ?></td>
            <td><?php foreach ($wh->eventsList() as $ev): ?><span class="badge badge-gray" style="margin-inline-end:4px;"><?= e($ev) ?></span><?php endforeach; ?></td>
            <td><span class="badge badge-<?= $wh->is_active ? 'green' : 'gray' ?>"><?= $wh->is_active ? 'Active' : 'Paused' ?></span></td>
            <td class="help-text"><?= $wh->last_triggered_at ? e($wh->last_triggered_at->diffForHumans()) . ' — ' . e($wh->last_status) : 'Never' ?></td>
            <td style="display:flex;gap:6px;">
              <form method="post" action="/app/integrations/webhooks/<?= $wh->id ?>/toggle">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light"><?= $wh->is_active ? 'Pause' : 'Resume' ?></button>
              </form>
              <form method="post" action="/app/integrations/webhooks/<?= $wh->id ?>/delete" onsubmit="return confirm('Remove this webhook?');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <form method="post" action="/app/integrations/webhooks" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <div class="form-group" style="margin:0;flex:1;min-width:260px;">
        <label>Webhook URL</label>
        <input type="url" name="url" placeholder="https://example.com/webhooks/buildxact" required>
      </div>
      <div class="form-group" style="margin:0;">
        <label>Events</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap;max-width:420px;">
          <?php foreach ($webhookEvents as $val => $label): ?>
            <label style="font-weight:400;font-size:13px;display:flex;align-items:center;gap:4px;">
              <input type="checkbox" name="events[]" value="<?= $val ?>" style="width:auto;"> <?= e($label) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
      <button type="submit" class="btn btn-outline">Add webhook</button>
    </form>
  </div>
</div>

@endsection
