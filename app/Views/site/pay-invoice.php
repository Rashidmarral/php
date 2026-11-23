<?php use App\Core\View; ?>
<section class="section" style="padding-top:48px;padding-bottom:80px;">
  <div class="container" style="max-width:520px;">
    <div class="card" style="margin-bottom:20px;">
      <h3 style="margin-bottom:4px;">Pay invoice <?= View::e($invoice['invoice_number']) ?></h3>
      <p class="help-text" style="margin-bottom:14px;">To <?= View::e($company['name'] ?? '') ?></p>
      <div class="total-row"><?= View::money((float) $invoice['total']) ?></div>
    </div>

    <div class="card">
      <p class="help-text" style="margin-bottom:14px;">Your card details are handled directly by Moyasar — they never pass through <?= View::e($company['name'] ?? 'our') ?>'s servers or BuildXact Saudi's.</p>
      <div class="mysr-form"
        data-amount="<?= (int) round((float) $invoice['total'] * 100) ?>"
        data-currency="SAR"
        data-description="Invoice <?= View::e($invoice['invoice_number']) ?>"
        data-publishable-api-key="<?= View::e($moyasarPublishableKey) ?>"
        data-callback-url="<?= View::e('/i/' . $token . '/pay/callback') ?>"
        data-methods="creditcard,applepay,stcpay">
      </div>
    </div>
    <p style="text-align:center;margin-top:16px;"><a href="/i/<?= View::e($token) ?>">← Back to invoice</a></p>
  </div>
</section>
<link rel="stylesheet" href="https://cdn.moyasar.com/mpf/1.15.0/moyasar.css">
<script src="https://cdn.moyasar.com/mpf/1.15.0/moyasar.js"></script>
