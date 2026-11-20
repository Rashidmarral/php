@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.billing.title') ?></h1>
</div>

<?php if ($pendingPayment): ?>
  <div class="alert" style="background:#fdf3e0;color:#b8860b;border:1px solid #f0dca4;">
    ⏳ <?= t('user.billing.pending_payment', ['ref' => e($pendingPayment['reference']), 'amount' => money((float)$pendingPayment['amount'])]) ?>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.billing.current_plan') ?></h3>
  <?php if ($currentPlan): ?>
    <p style="font-size:20px;font-weight:800;color:var(--brand-dark);"><?= e($currentPlan['name']) ?></p>
    <p class="help-text">
      <?= t('common.status') ?>: <span class="badge badge-<?= $company['status']==='active'?'green':'yellow' ?>"><?= e($company['status']) ?></span>
      <?php if ($subscription): ?> · <?= t('user.billing.billing_cycle') ?> <?= e(ucfirst($subscription['billing_cycle'])) ?> · <?= t('user.billing.renews') ?> <?= e($subscription['current_period_end']) ?><?php endif; ?>
    </p>
  <?php else: ?>
    <p class="help-text"><?= t('user.billing.no_active_plan') ?></p>
  <?php endif; ?>
</div>

<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.billing.change_plan') ?></h3>

  <div style="display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:24px;">
    <span id="cycle-label-monthly" style="font-weight:700;"><?= t('billing.monthly') ?></span>
    <label class="cycle-switch">
      <input type="checkbox" id="cycle-toggle">
      <span class="cycle-slider"></span>
    </label>
    <span id="cycle-label-yearly" style="color:var(--muted);"><?= t('billing.yearly') ?> <span class="badge badge-green"><?= t('user.billing.months_free') ?></span></span>
  </div>

  <div class="grid grid-3">
    <?php foreach ($plans as $plan): $features = json_decode($plan['features'], true) ?: []; ?>
      <div class="pricing-card card <?= $currentPlan && $currentPlan['id']==$plan['id'] ? 'featured' : '' ?>">
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?><span class="badge-featured"><?= t('user.billing.current') ?></span><?php endif; ?>
        <h3><?= e($plan['name']) ?></h3>
        <div class="price">
          <span class="price-amount"
            data-monthly="<?= number_format((float)$plan['price_monthly'], 0) ?>"
            data-yearly="<?= number_format((float)$plan['price_yearly'] / 12, 0) ?>"
          ><?= number_format((float)$plan['price_monthly'], 0) ?></span> <small>SAR/mo</small>
        </div>
        <p class="help-text price-yearly-note" style="display:none;margin-top:-8px;"><?= number_format((float)$plan['price_yearly'], 0) ?> SAR/year, billed yearly</p>
        <ul class="plan-features"><?php foreach ($features as $f): ?><li><?= e($f) ?></li><?php endforeach; ?></ul>
        <?php if ($currentPlan && $currentPlan['id']==$plan['id']): ?>
          <button class="btn btn-light btn-block" disabled><?= t('user.billing.current_plan_btn') ?></button>
        <?php else: ?>
          <a href="/app/billing/checkout?plan=<?= urlencode($plan['slug']) ?>&cycle=monthly" class="btn btn-primary btn-block plan-cta" data-slug="<?= urlencode($plan['slug']) ?>"><?= t('user.billing.choose_plan') ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="help-text" style="margin-top:16px;"><?= t('user.billing.pay_methods_hint') ?></p>
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
  <h3><?= t('user.billing.payment_history') ?></h3>
  <?php if (empty($payments)): ?>
    <p class="help-text"><?= t('user.billing.no_payments_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('common.reference') ?></th><th><?= t('common.method') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th></tr></thead>
      <tbody>
      <?php foreach ($payments as $p): ?>
        <tr>
          <td><?= e($p['created_at']) ?></td>
          <td><?= e($p['reference']) ?></td>
          <td><?= e(strtoupper($p['method'])) ?></td>
          <td><?= money((float)$p['amount']) ?></td>
          <td><span class="badge badge-<?= $p['status']==='paid'?'green':($p['status']==='pending'?'yellow':'red') ?>"><?= e($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
