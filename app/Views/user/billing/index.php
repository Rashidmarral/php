<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Billing & Subscription</h1>
</div>

<?php if ($pendingPayment): ?>
  <div class="alert" style="background:#fdf3e0;color:#b8860b;border:1px solid #f0dca4;">
    ⏳ A bank transfer payment (<?= View::e($pendingPayment['reference']) ?>, <?= View::money((float)$pendingPayment['amount']) ?>) is awaiting admin approval.
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h3>Current plan</h3>
  <?php if ($currentPlan): ?>
    <p style="font-size:20px;font-weight:800;color:var(--brand-dark);"><?= View::e($currentPlan['name']) ?></p>
    <p class="help-text">
      Status: <span class="badge badge-<?= $company['status']==='active'?'green':'yellow' ?>"><?= View::e($company['status']) ?></span>
      <?php if ($subscription): ?> · Billing cycle: <?= View::e(ucfirst($subscription['billing_cycle'])) ?> · Renews: <?= View::e($subscription['current_period_end']) ?><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="help-text">No active plan.</p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3>Change plan</h3>

  <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:24px;">
    <span id="cycle-label-monthly" style="font-weight:700;">Monthly</span>
    <label class="cycle-switch">
      <input type="checkbox" id="cycle-toggle">
      <span class="cycle-slider"></span>
    </label>
    <span id="cycle-label-yearly" style="color:var(--muted);">Yearly <span class="badge badge-green">2 months free</span></span>
  </div>

  <div class="grid grid-3">
    <?php foreach ($plans as $plan): $features = json_decode($plan['features'], true) ?: []; ?>
      <div class="pricing-card card <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'featured' : '' ?>">
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?><span class="badge-featured">Current</span><?php endif; ?>
        <h3><?= View::e($plan['name']) ?></h3>
        <div class="price">
          <span class="price-amount"
            data-monthly="<?= number_format((float)$plan['price_monthly'], 0) ?>"
            data-yearly="<?= number_format((float)$plan['price_yearly'] / 12, 0) ?>"
          ><?= number_format((float)$plan['price_monthly'], 0) ?></span> <small>SAR/mo</small>
        </div>
        <p class="help-text price-yearly-note" style="display:none;margin-top:-8px;"><?= number_format((float)$plan['price_yearly'], 0) ?> SAR/year, billed yearly</p>
        <ul class="plan-features"><?php foreach ($features as $f): ?><li><?= View::e($f) ?></li><?php endforeach; ?></ul>
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?>
          <button class="btn btn-light btn-block" disabled>Current plan</button>
        <?php else: ?>
          <a href="/app/billing/checkout?plan=<?= urlencode($plan['slug']) ?>&cycle=monthly" class="btn btn-primary btn-block plan-cta" data-slug="<?= urlencode($plan['slug']) ?>">Choose this plan</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="help-text" style="margin-top:16px;">Pay by bank transfer (held for admin approval) or card via Moyasar, if enabled by your platform administrator.</p>
</div>

<script>
(function() {
  const toggle = document.getElementById('cycle-toggle');
  const amounts = document.querySelectorAll('.price-amount');
  const monthlyLabel = document.getElementById('cycle-label-monthly');
  const yearlyLabel = document.getElementById('cycle-label-yearly');
  const yearlyNotes = document.querySelectorAll('.price-yearly-note');
  const ctas = document.querySelectorAll('.plan-cta');

  toggle.addEventListener('change', () => {
    const yearly = toggle.checked;
    amounts.forEach(el => { el.textContent = yearly ? el.dataset.yearly : el.dataset.monthly; });
    yearlyNotes.forEach(el => { el.style.display = yearly ? '' : 'none'; });
    monthlyLabel.style.fontWeight = yearly ? '400' : '700';
    monthlyLabel.style.color = yearly ? 'var(--muted)' : '';
    yearlyLabel.style.fontWeight = yearly ? '700' : '400';
    yearlyLabel.style.color = yearly ? '' : 'var(--muted)';
    ctas.forEach(a => { a.href = '/app/billing/checkout?plan=' + a.dataset.slug + '&cycle=' + (yearly ? 'yearly' : 'monthly'); });
  });
})();
</script>

<div class="card">
  <h3>Payment history</h3>
  <?php if (empty($payments)): ?>
    <p class="help-text">No payments yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Date</th><th>Reference</th><th>Method</th><th>Amount</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= View::e($p['created_at']) ?></td>
          <td><?= View::e($p['reference']) ?></td>
          <td><?= View::e(strtoupper($p['method'])) ?></td>
          <td><?= View::money((float)$p['amount']) ?></td>
          <td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':'red') ?>"><?= View::e($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
