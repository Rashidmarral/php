<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('user.billing.checkout') ?></h1>
  <a href="/app/billing" class="btn btn-light"><?= t('user.billing.back_to_billing') ?></a>
</div>

<div class="card" style="max-width:520px;margin-bottom:20px;">
  <h3 style="margin-bottom:4px;"><?= View::e($plan['name']) ?> plan</h3>
  <p class="help-text" style="margin-bottom:14px;"><?= ucfirst($cycle) ?> <?= t('user.billing.billing_suffix') ?></p>
  <div class="total-row"><?= View::money($amount) ?><?= $cycle === 'yearly' ? ' ' . t('user.billing.per_year') : ' ' . t('user.billing.per_month') ?></div>
</div>

<div class="tabs" id="method-tabs">
  <a href="#bank" class="tab-link active" data-tab="bank"><?= t('user.billing.bank_transfer') ?></a>
  <?php if ($moyasarConfigured): ?>
    <a href="#card" class="tab-link" data-tab="card"><?= t('user.billing.card_option') ?></a>
  <?php endif; ?>
</div>

<div id="tab-bank" class="tab-panel">
  <?php if ($bankTransferEnabled): ?>
    <div class="card" style="max-width:520px;">
      <p class="help-text"><?= t('user.billing.transfer_hint') ?></p>
      <table class="data" style="margin:14px 0;">
        <tbody>
          <tr><td><?= t('user.billing.bank') ?></td><td><?= View::e($bank['name'] ?: '—') ?></td></tr>
          <tr><td><?= t('user.billing.account_name') ?></td><td><?= View::e($bank['accountName'] ?: '—') ?></td></tr>
          <tr><td><?= t('common.iban') ?></td><td><?= View::e($bank['iban'] ?: '—') ?></td></tr>
          <tr><td><?= t('user.billing.account_number') ?></td><td><?= View::e($bank['accountNumber'] ?: '—') ?></td></tr>
        </tbody>
      </table>
      <form method="post" action="/app/billing/bank-transfer" enctype="multipart/form-data">
        <?= Csrf::field() ?>
        <input type="hidden" name="plan" value="<?= View::e($plan['slug']) ?>">
        <input type="hidden" name="cycle" value="<?= View::e($cycle) ?>">
        <div class="form-group">
          <label><?= t('user.billing.transfer_receipt') ?></label>
          <input type="file" name="receipt" accept="application/pdf,image/jpeg,image/png">
        </div>
        <button type="submit" class="btn btn-primary btn-block"><?= t('user.billing.made_transfer') ?></button>
      </form>
    </div>
  <?php else: ?>
    <div class="card" style="max-width:520px;"><p class="help-text"><?= t('user.billing.bank_unavailable') ?></p></div>
  <?php endif; ?>
</div>

<?php if ($moyasarConfigured): ?>
<div id="tab-card" class="tab-panel" style="display:none;">
  <div class="card" style="max-width:520px;">
    <p class="help-text" style="margin-bottom:14px;"><?= t('user.billing.card_hint') ?></p>
    <div class="mysr-form"
      data-amount="<?= (int) round($amount * 100) ?>"
      data-currency="SAR"
      data-description="<?= View::e($plan['name']) ?> plan (<?= View::e($cycle) ?>)"
      data-publishable-api-key="<?= View::e($moyasarPublishableKey) ?>"
      data-callback-url="<?= View::e('/app/billing/moyasar-callback?plan=' . urlencode($plan['slug']) . '&cycle=' . urlencode($cycle)) ?>"
      data-methods="creditcard,applepay,stcpay"
      data-save-card="true">
    </div>
    <p class="help-text" id="card-loading-hint"><?= t('user.billing.loading_payment_form') ?></p>
  </div>
</div>
<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
<?php endif; ?>

<script>
document.querySelectorAll('.tab-link').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    link.classList.add('active');
    document.getElementById('tab-' + link.dataset.tab).style.display = 'block';

    // Moyasar's widget sizes itself against the DOM when its script runs — loading it
    // eagerly while this tab sits under display:none gives it a zero-width container and
    // it silently fails to render anything. Load it lazily, only once the tab (and its
    // real width) is actually visible.
    if (link.dataset.tab === 'card' && !window.__moyasarLoaded) {
      window.__moyasarLoaded = true;
      const script = document.createElement('script');
      script.src = 'https://cdn.moyasar.com/mpf/1.15.0/moyasar.js';
      script.onload = () => { const hint = document.getElementById('card-loading-hint'); if (hint) hint.remove(); };
      script.onerror = () => { const hint = document.getElementById('card-loading-hint'); if (hint) hint.textContent = 'Could not load the payment form — please check your connection and try again, or use bank transfer.'; };
      document.body.appendChild(script);
    }
  });
});
</script>
