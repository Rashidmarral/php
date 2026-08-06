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
  <div class="grid grid-3">
    <?php foreach ($plans as $plan): $features = json_decode($plan['features'], true) ?: []; ?>
      <div class="pricing-card card <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'featured' : '' ?>">
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?><span class="badge-featured">Current</span><?php endif; ?>
        <h3><?= View::e($plan['name']) ?></h3>
        <div class="price"><?= number_format((float)$plan['price_monthly'],0) ?> <small>SAR/mo</small></div>
        <ul class="plan-features"><?php foreach ($features as $f): ?><li><?= View::e($f) ?></li><?php endforeach; ?></ul>
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?>
          <button class="btn btn-light btn-block" disabled>Current plan</button>
        <?php else: ?>
          <a href="/app/billing/checkout?plan=<?= urlencode($plan['slug']) ?>&cycle=monthly" class="btn btn-primary btn-block">Choose this plan</a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="help-text" style="margin-top:16px;">Pay by bank transfer (held for admin approval) or card via Moyasar, if enabled by your platform administrator.</p>
</div>

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
