<?php
use App\Core\View;
use App\Core\Csrf;
$features = $plan ? implode("\n", json_decode($plan['features'], true) ?: []) : '';
$activeFlags = $plan ? (json_decode($plan['feature_flags'] ?? '{}', true) ?: []) : [];
?>
<div class="page-head">
  <h1><?= $plan ? t('admin.plan.edit') : t('admin.plan.new') ?></h1>
  <a href="/admin/plans" class="btn btn-light">← <?= t('admin.plan.back') ?></a>
</div>

<form method="post" action="<?= $plan ? '/admin/plans/' . $plan['id'] : '/admin/plans' ?>" class="card" style="max-width:760px;">
  <?= Csrf::field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.plan.name_en') ?></label><input type="text" name="name" required value="<?= View::e($plan['name'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.plan.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= View::e($plan['name_ar'] ?? '') ?>" placeholder="اسم الباقة"></div>
  </div>
  <div class="form-group"><label><?= t('common.slug') ?></label><input type="text" name="slug" required value="<?= View::e($plan['slug'] ?? '') ?>" placeholder="e.g. professional"></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.plan.tagline_en') ?></label><input type="text" name="tagline" value="<?= View::e($plan['tagline'] ?? '') ?>"></div>
    <div class="form-group"><label><?= t('admin.plan.tagline_ar') ?></label><input type="text" name="tagline_ar" dir="rtl" value="<?= View::e($plan['tagline_ar'] ?? '') ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.plan.price_monthly') ?></label><input type="number" step="0.01" name="price_monthly" value="<?= View::e((string)($plan['price_monthly'] ?? 0)) ?>"></div>
    <div class="form-group"><label><?= t('admin.plan.price_yearly') ?></label><input type="number" step="0.01" name="price_yearly" value="<?= View::e((string)($plan['price_yearly'] ?? 0)) ?>"></div>
  </div>
  <div class="form-row">
    <div class="form-group"><label><?= t('admin.plan.max_users') ?></label><input type="number" name="max_users" value="<?= View::e((string)($plan['max_users'] ?? 5)) ?>"></div>
    <div class="form-group"><label><?= t('admin.plan.max_projects') ?></label><input type="number" name="max_projects" value="<?= View::e((string)($plan['max_projects'] ?? 10)) ?>"></div>
  </div>
  <div class="form-group" style="max-width:260px;">
    <label><?= t('admin.plan.consultations_month') ?></label>
    <input type="number" min="0" name="consultation_quota_monthly" value="<?= View::e((string)($plan['consultation_quota_monthly'] ?? 0)) ?>">
    <p class="help-text">How many live consultations with our engineers companies on this plan get each month. 0 disables the feature for this plan.</p>
  </div>
  <div class="form-group">
    <label><?= t('admin.plan.features_line') ?></label>
    <textarea name="features" rows="6"><?= View::e($features) ?></textarea>
    <p class="help-text">Shown as marketing bullet points on the pricing page.</p>
  </div>
  <div class="form-group">
    <label><?= t('admin.plan.module_access') ?></label>
    <p class="help-text" style="margin-top:-2px;">Controls which parts of the user panel companies on this plan can actually use. Core features (projects, estimates, invoices, clients, schedule, team) are always included and aren't listed here.</p>
    <?php
      $featureGroups = [
        t('admin.plan.group_estimating') => ['ai_estimate_generator', 'estimate_templates', 'quick_estimate', 'leads'],
        t('admin.plan.group_delivery') => ['takeoff', 'change_orders', 'project_photos', 'documents'],
        t('admin.plan.group_money') => ['online_invoice_payments', 'zatca_phase2', 'reports'],
        t('admin.plan.group_vendors') => ['suppliers', 'materials'],
        t('admin.plan.group_compliance') => ['compliance', 'client_portal'],
        t('admin.plan.group_platform') => ['integrations'],
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
    <div class="form-group"><label><?= t('common.sort_order') ?></label><input type="number" name="sort_order" value="<?= View::e((string)($plan['sort_order'] ?? 0)) ?>"></div>
    <div class="form-group">
      <label><?= t('admin.plan.visible_pricing') ?></label>
      <select name="is_active">
        <option value="1" <?= (!$plan || $plan['is_active']) ? 'selected' : '' ?>><?= t('common.yes') ?></option>
        <option value="0" <?= ($plan && !$plan['is_active']) ? 'selected' : '' ?>><?= t('common.no') ?></option>
      </select>
    </div>
  </div>
  <button type="submit" class="btn btn-primary"><?= $plan ? t('common.save_changes') : t('admin.plan.create') ?></button>
</form>
