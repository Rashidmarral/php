<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Checkout</h1>
  <a href="/app/billing" class="btn btn-light">← Back to billing</a>
</div>

<div class="card" style="max-width:520px;margin-bottom:20px;">
  <h3 style="margin-bottom:4px;"><?= View::e($plan['name']) ?> plan</h3>
  <p class="help-text" style="margin-bottom:14px;"><?= ucfirst($cycle) ?> billing</p>
  <div class="total-row"><?= View::money($amount) ?><?= $cycle === 'yearly' ? ' / year' : ' / month' ?></div>
</div>

<div class="tabs" id="method-tabs">
  <a href="#bank" class="tab-link active" data-tab="bank">🏦 Bank Transfer</a>
  <?php if ($moyasarConfigured): ?>
    <a href="#card" class="tab-link" data-tab="card">💳 Card (mada / Visa / Mastercard)</a>
  <?php endif; ?>
</div>

<div id="tab-bank" class="tab-panel">
  <?php if ($bankTransferEnabled): ?>
    <div class="card" style="max-width:520px;">
      <p class="help-text">Transfer the exact amount above to the account below, then confirm your request. Your plan activates once our team verifies the transfer (usually within one business day).</p>
      <table class="data" style="margin:14px 0;">
        <tbody>
          <tr><td>Bank</td><td><?= View::e($bank['name'] ?: '—') ?></td></tr>
          <tr><td>Account name</td><td><?= View::e($bank['accountName'] ?: '—') ?></td></tr>
          <tr><td>IBAN</td><td><?= View::e($bank['iban'] ?: '—') ?></td></tr>
          <tr><td>Account number</td><td><?= View::e($bank['accountNumber'] ?: '—') ?></td></tr>
        </tbody>
      </table>
      <form method="post" action="/app/billing/bank-transfer">
        <?= Csrf::field() ?>
        <input type="hidden" name="plan" value="<?= View::e($plan['slug']) ?>">
        <input type="hidden" name="cycle" value="<?= View::e($cycle) ?>">
        <button type="submit" class="btn btn-primary btn-block">I've made the transfer</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card" style="max-width:520px;"><p class="help-text">Bank transfer isn't currently available. Please use a card payment or contact your platform administrator.</p></div>
  <?php endif; ?>
</div>

<?php if ($moyasarConfigured): ?>
<div id="tab-card" class="tab-panel" style="display:none;">
  <div class="card" style="max-width:520px;">
    <p class="help-text" style="margin-bottom:14px;">Your card details are handled directly by Moyasar — they never pass through our servers.</p>
    <div class="mysr-form"
      data-amount="<?= (int) round($amount * 100) ?>"
      data-currency="SAR"
      data-description="<?= View::e($plan['name']) ?> plan (<?= View::e($cycle) ?>)"
      data-publishable-api-key="<?= View::e($moyasarPublishableKey) ?>"
      data-callback-url="<?= View::e('/app/billing/moyasar-callback?plan=' . urlencode($plan['slug']) . '&cycle=' . urlencode($cycle)) ?>"
      data-methods="creditcard,applepay">
    </div>
  </div>
</div>
<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
<script src="https://cdn.moyasar.com/mpf/1.15.0/moyasar.js"></script>
<?php endif; ?>

<script>
document.querySelectorAll('.tab-link').forEach(link => {
  link.addEventListener('click', (e) => {
    e.preventDefault();
    document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
    link.classList.add('active');
    document.getElementById('tab-' + link.dataset.tab).style.display = 'block';
  });
});
</script>
