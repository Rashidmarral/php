<?php
use App\Core\View;
use App\Core\Csrf;
$features = $plan ? implode("\n", json_decode($plan['features'], true) ?: []) : '';
$activeFlags = $plan ? (json_decode($plan['feature_flags'] ?? '{}', true) ?: []) : [];
?>
<div class="page-head">
  <h1><?= $plan ? 'Edit Plan' : 'New Plan' ?></h1>
  <a href="/admin/plans" class="btn btn-light">← Back to plans</a>
</div>

<form method="post" action="<?= $plan ? '/admin/plans/' . $plan['id'] : '/admin/plans' ?>" class="card" style="max-width:760px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label>Plan name (English)</label><input type="text" name="name" required value="<?= View::e($plan['name'] ?? '') ?>"></div>
    <div class="form-group"><label>Plan name (Arabic)</label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($plan['name_ar'] ?? '') ?>" placeholder="اسم الباقة"></div>
  </div>
  <div class="form-group"><label>Slug</label><input type="text" name="slug" required value="<?= View::e($plan['slug'] ?? '') ?>" placeholder="e.g. professional"></div>
  <div class="form-row">
    <div class="form-group"><label>Tagline (English)</label><input type="text" name="tagline" value="<?= View::e($plan['tagline'] ?? '') ?>"></div>
    <div class="form-group"><label>Tagline (Arabic)</label><input type="text" name="tagline_ar" dir="rtl" value="<?= View::e($plan['tagline_ar'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Price / month (SAR)</label><input type="number" step="0.01" name="price_monthly" value="<?= View::e((string)($plan['price_monthly'] ?? 0)) ?>"></div>
    <div class="form-group"><label>Price / year (SAR)</label><input type="number" step="0.01" name="price_yearly" value="<?= View::e((string)($plan['price_yearly'] ?? 0)) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Max users</label><input type="number" name="max_users" value="<?= View::e((string)($plan['max_users'] ?? 5)) ?>"></div>
    <div class="form-group"><label>Max projects</label><input type="number" name="max_projects" value="<?= View::e((string)($plan['max_projects'] ?? 10)) ?>"></div>
  </div>
  <div class="form-group" style="max-width:260px;">
    <label>Expert consultations / month</label>
    <input type="number" min="0" name="consultation_quota_monthly" value="<?= View::e((string)($plan['consultation_quota_monthly'] ?? 0)) ?>">
    <p class="help-text">How many live consultations with our engineers companies on this plan get each month. 0 disables the feature for this plan.</p>
  </div>
  <div class="form-group">
    <label>Features (one per line)</label>
    <textarea name="features" rows="6"><?= View::e($features) ?></textarea>
    <p class="help-text">Shown as marketing bullet points on the pricing page.</p>
  </div>
  <div class="form-group">
    <label>Module access</label>
    <p class="help-text" style="margin-top:-2px;">Controls which parts of the user panel companies on this plan can actually use. Core features (projects, estimates, invoices, clients, schedule, team) are always included and aren't listed here.</p>
    <?php
      $featureGroups = [
        'Estimating & Sales' => ['ai_estimate_generator', 'estimate_templates', 'quick_estimate', 'leads'],
        'Project Delivery' => ['takeoff', 'change_orders', 'project_photos', 'documents'],
        'Money' => ['online_invoice_payments', 'zatca_phase2', 'reports'],
        'Vendors & Materials' => ['suppliers', 'materials'],
        'Compliance & Clients' => ['compliance', 'client_portal'],
        'Platform' => ['integrations'],
      ];
      $grouped = array_merge(...array_values($featureGroups));
      $ungrouped = array_diff(array_keys($allFeatures), $grouped);
    ?>
    <?php foreach ($featureGroups as $groupLabel => $keys): ?>
      <div style="margin-bottom:12px;">
        <div style="font-size:12px;text-transform:uppercase;letter-spacing:.03em;color:var(--muted);margin-bottom:6px;"><?= View::e($groupLabel) ?></div>
        <div class="grid grid-2" style="gap:8px;">
          <?php foreach ($keys as $key): if (!isset($allFeatures[$key])) continue; ?>
            <label style="font-weight:400;font-size:14px;">
              <input type="checkbox" name="feature_<?= $key ?>" value="1" style="width:auto;display:inline-block;" <?= !empty($activeFlags[$key]) ? 'checked' : '' ?>>
              <?= View::e($allFeatures[$key]) ?>
            </label>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
    <?php if (!empty($ungrouped)): ?>
      <div class="grid grid-2" style="gap:8px;">
        <?php foreach ($ungrouped as $key): ?>
          <label style="font-weight:400;font-size:14px;">
            <input type="checkbox" name="feature_<?= $key ?>" value="1" style="width:auto;display:inline-block;" <?= !empty($activeFlags[$key]) ? 'checked' : '' ?>>
            <?= View::e($allFeatures[$key]) ?>
          </label>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="form-row">
    <div class="form-group"><label>Sort order</label><input type="number" name="sort_order" value="<?= View::e((string)($plan['sort_order'] ?? 0)) ?>"></div>
    <div class="form-group">
      <label>Visible on pricing page</label>
      <select name="is_active">
        <option value="1" <?= (!$plan || $plan['is_active']) ? 'selected' : '' ?>>Yes</option>
        <option value="0" <?= ($plan && !$plan['is_active']) ? 'selected' : '' ?>>No</option>
      </select>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= $plan ? 'Save changes' : 'Create plan' ?></button>
</form>
