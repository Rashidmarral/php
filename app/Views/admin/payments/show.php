<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1>Transaction #<?= $payment['id'] ?></h1>
    <p class="help-text" style="margin-top:4px;"><a href="/admin/companies/<?= $payment['company_id'] ?>"><?= View::e($payment['company_name']) ?></a> · <?= View::e($payment['created_at']) ?></p>
  </div>
  <a href="/admin/payments" class="btn btn-secondary">← Back to payments</a>
</div>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi"><div class="label">Amount</div><div class="value" style="font-size:16px;"><?= View::money((float)$payment['amount']) ?></div></div>
  <div class="kpi"><div class="label">Method</div><div class="value" style="font-size:16px;"><?= View::e(strtoupper($payment['method'])) ?></div></div>
  <div class="kpi"><div class="label">Status</div><div class="value" style="font-size:16px;"><span class="badge badge-<?= $payment['status']==='paid'?'green':($payment['status']==='pending'?'yellow':($payment['status']==='refunded'?'blue':'red')) ?>"><?= View::e($payment['status']) ?></span></div></div>
  <div class="kpi"><div class="label">Reviewed</div><div class="value" style="font-size:16px;"><?= View::e($payment['reviewed_at'] ?: '—') ?></div></div>
</div>

<?php if (!empty($payment['proof_file_path'])): ?>
  <div class="card" style="margin-bottom:24px;max-width:520px;">
    <h3>Bank transfer proof</h3>
    <a href="<?= View::e($payment['proof_file_path']) ?>" target="_blank" rel="noopener" class="btn btn-outline">View uploaded proof →</a>
  </div>
<?php endif; ?>

<?php if ($payment['status'] === 'pending'): ?>
  <div class="card" style="margin-bottom:24px;max-width:520px;">
    <h3>Quick decision</h3>
    <p class="help-text">Approves as-is (using the plan/cycle already recorded on this transaction) or rejects it outright.</p>
    <div style="display:flex;gap:8px;">
      <form method="post" action="/admin/payments/<?= $payment['id'] ?>/approve" onsubmit="return confirm('Approve this payment and activate the company plan?');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-primary">Approve</button>
      </form>
      <form method="post" action="/admin/payments/<?= $payment['id'] ?>/reject" onsubmit="return confirm('Reject this payment?');">
        <?= Csrf::field() ?>
        <button type="submit" class="btn btn-light">Reject</button>
      </form>
    </div>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;max-width:520px;">
  <h3>Edit transaction</h3>
  <p class="help-text">Correct the recorded details of this transaction — amount, reference, method, or status (including marking it refunded). This does not by itself change the company's plan.</p>
  <form method="post" action="/admin/payments/<?= $payment['id'] ?>/update">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group"><label>Amount (SAR)</label><input type="number" step="0.01" name="amount" value="<?= View::e((string)$payment['amount']) ?>"></div>
      <div class="form-group">
        <label>Status</label>
        <select name="status">
          <?php foreach (['paid'=>'Paid','pending'=>'Pending','failed'=>'Failed','refunded'=>'Refunded'] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= $payment['status'] === $val ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Method</label><input type="text" name="method" value="<?= View::e($payment['method']) ?>"></div>
      <div class="form-group"><label>Reference</label><input type="text" name="reference" value="<?= View::e($payment['reference']) ?>"></div>
    </div>
    <button type="submit" class="btn btn-primary">Save transaction</button>
  </form>
</div>

<div class="card" style="max-width:520px;">
  <h3>Reassign package</h3>
  <p class="help-text">Change which plan/cycle this transaction grants and push it live on the company immediately — use this to correct a payment that was tied to the wrong package.</p>
  <form method="post" action="/admin/payments/<?= $payment['id'] ?>/apply-plan" onsubmit="return confirm('Apply this plan to the company now?');" style="display:flex;gap:8px;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;">
      <label>Plan</label>
      <select name="plan_id">
        <?php foreach ($plans as $p): ?>
          <option value="<?= $p['id'] ?>" <?= ($payment['plan_id'] ?? null) == $p['id'] ? 'selected' : '' ?>><?= View::e($p['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label>Cycle</label>
      <select name="billing_cycle">
        <option value="monthly" <?= ($payment['billing_cycle'] ?? '') === 'monthly' ? 'selected' : '' ?>>Monthly</option>
        <option value="yearly" <?= ($payment['billing_cycle'] ?? '') === 'yearly' ? 'selected' : '' ?>>Yearly</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">Apply to company</button>
  </form>
</div>
