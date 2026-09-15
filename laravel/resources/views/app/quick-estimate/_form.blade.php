{{--
  Shared by index.blade.php (create) and edit.blade.php (edit): the sticky
  summary sidebar, the region/foundation/quality-tier picker cards, the
  add-on cards, and the live recalc() JS that mirrors QuickEstimateCalc::compute().
  Callers pass $formAction and $submitLabel, plus prefill values (all optional —
  the create form leaves them unset and everything defaults to empty/zero):
  $projectName, $selectedClientId, $selectedRegionId, $selectedFoundationId,
  $selectedTierId, $totalArea, $discountPercent, $selectedAddonIds (array of
  addon ids), $addonQty (addon id => manual quantity).
--}}
<?php
  $projectName = $projectName ?? '';
  $selectedClientId = $selectedClientId ?? null;
  $selectedRegionId = $selectedRegionId ?? null;
  $selectedFoundationId = $selectedFoundationId ?? null;
  $selectedTierId = $selectedTierId ?? null;
  $totalArea = $totalArea ?? 0;
  $discountPercent = $discountPercent ?? 0;
  $selectedAddonIds = $selectedAddonIds ?? [];
  $addonQty = $addonQty ?? [];
?>

<form method="post" action="<?= e($formAction) ?>" id="qe-form">
  <?= csrf_field() ?>
  <div class="qe-layout">

    <!-- Sticky summary sidebar -->
    <div class="card qe-summary">
      <h3 style="font-size:14px;"><?= t('qe.summary') ?></h3>
      <div class="mini-grid">
        <div class="mini-kpi"><div class="l"><?= t('qe.addons') ?></div><div class="v" id="qe-addons-total">0.00</div></div>
        <div class="mini-kpi"><div class="l"><?= t('qe.foundation') ?></div><div class="v" id="qe-foundation-total">0.00</div></div>
      </div>
      <div class="mini-grid">
        <div class="mini-kpi"><div class="l"><?= t('qe.region') ?></div><div class="v" id="qe-region-mult">1.00x</div></div>
        <div class="mini-kpi"><div class="l"><?= t('qe.discount') ?></div><div class="v" id="qe-discount-pct">0%</div></div>
      </div>
      <div class="mini-grid">
        <div class="mini-kpi"><div class="l"><?= t('qe.quality_tier') ?></div><div class="v" id="qe-tier-mult">1.00x</div></div>
      </div>

      <div class="total-box">
        <div class="label"><?= t('qe.total') ?></div>
        <div class="value"><span id="qe-total">0.00</span> SAR</div>
      </div>

      <div class="breakdown">
        <div><span><?= t('qe.subtotal') ?></span><span id="qe-subtotal">0.00</span></div>
        <div><span><?= t('qe.discount') ?></span><span id="qe-discount-amt">-0.00</span></div>
        <div><span id="qe-vat-label"><?= t('qe.vat', ['rate' => $vatRate]) ?></span><span id="qe-vat-amt">0.00</span></div>
      </div>
      <div class="breakdown" style="margin-top:8px;">
        <div><strong><?= t('qe.per_sqm') ?></strong></div>
        <div><span id="qe-per-sqm">0.00</span> SAR/m²</div>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;"><?= $submitLabel ?></button>
    </div>

    <!-- Main form -->
    <div>
      <div class="card" style="margin-bottom:18px;">
        <h3><?= t('qe.project_details') ?></h3>
        <div class="form-row">
          <div class="form-group">
            <label><?= t('qe.project_name') ?></label>
            <input type="text" name="project_name" placeholder="<?= t('qe.project_name') ?>" value="<?= e($projectName) ?>">
          </div>
          <div class="form-group">
            <label><?= t('user.quick_estimate.client_optional') ?></label>
            <select name="client_id">
              <option value=""><?= t('user.quick_estimate.no_client_linked') ?></option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ((int) $selectedClientId === (int) $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px;">
        <h3>📍 <?= t('qe.region') ?></h3>
        <div class="qe-pick-grid" id="qe-regions">
          <?php foreach ($regions as $r): $name = app()->getLocale() === 'ar' ? $r['name_ar'] : $r['name_en']; $checked = (int) $selectedRegionId === (int) $r['id']; ?>
            <label class="qe-pick-card<?= $checked ? ' selected' : '' ?>" data-id="<?= $r['id'] ?>" data-price="<?= $r['price_per_sqm'] ?>" data-mult="<?= $r['multiplier'] ?>">
              <input type="radio" name="region_id" value="<?= $r['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <div class="name"><?= e($name) ?></div>
              <div class="price">SAR <?= number_format((float)$r['price_per_sqm'],2) ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <h3>🧱 <?= t('qe.foundation_type') ?></h3>
        <div class="qe-pick-grid" id="qe-foundations" style="grid-template-columns:repeat(2,1fr);">
          <?php foreach ($foundations as $f): $name = app()->getLocale() === 'ar' ? $f['name_ar'] : $f['name_en']; $desc = app()->getLocale() === 'ar' ? $f['description_ar'] : $f['description_en']; $checked = (int) $selectedFoundationId === (int) $f['id']; ?>
            <label class="qe-pick-card<?= $checked ? ' selected' : '' ?>" data-id="<?= $f['id'] ?>" data-price="<?= $f['price_per_sqm'] ?>">
              <input type="radio" name="foundation_id" value="<?= $f['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <div class="name"><?= e($name) ?></div>
              <div class="desc"><?= e($desc) ?></div>
              <div class="price">SAR/m² <?= number_format((float)$f['price_per_sqm'],2) ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <h3>✨ <?= t('qe.quality_tier') ?></h3>
        <div class="qe-pick-grid" id="qe-tiers" style="grid-template-columns:repeat(3,1fr);">
          <?php foreach ($qualityTiers as $qt): $name = app()->getLocale() === 'ar' ? $qt['name_ar'] : $qt['name_en']; $checked = (int) $selectedTierId === (int) $qt['id']; ?>
            <label class="qe-pick-card<?= $checked ? ' selected' : '' ?>" data-id="<?= $qt['id'] ?>" data-mult="<?= $qt['multiplier'] ?>">
              <input type="radio" name="quality_tier_id" value="<?= $qt['id'] ?>" <?= $checked ? 'checked' : '' ?>>
              <div class="name"><?= e($name) ?></div>
              <div class="price"><?= number_format((float)$qt['multiplier'],2) ?>x</div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label><?= t('qe.discount') ?> (%)</label>
            <input type="number" name="discount_percent" id="qe-discount-input" min="0" max="100" value="<?= e((string) $discountPercent) ?>">
          </div>
          <div class="form-group">
            <label><?= t('qe.total_area') ?> (m²)</label>
            <input type="number" name="total_area" id="qe-area-input" min="0" value="<?= e((string) $totalArea) ?>" required>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px;">
        <h3>🔧 <?= t('qe.addons') ?></h3>
        <p class="help-text" style="margin-top:-8px;"><?= t('qe.addons_hint') ?></p>
        <div class="qe-addon-grid" id="qe-addons">
          <?php foreach ($addons as $a): $name = app()->getLocale() === 'ar' ? $a['name_ar'] : $a['name_en']; $desc = app()->getLocale() === 'ar' ? $a['description_ar'] : $a['description_en']; $manual = ($a['qty_mode'] ?? 'area') === 'manual'; $checked = in_array((int) $a['id'], $selectedAddonIds, true); $qtyValue = $addonQty[$a['id']] ?? 1; ?>
            <label class="qe-addon-card<?= $checked ? ' selected' : '' ?>" for="qe-addon-<?= $a['id'] ?>" data-id="<?= $a['id'] ?>" data-price="<?= $a['unit_price'] ?>" data-mode="<?= $manual ? 'manual' : 'area' ?>">
              <div>
                <div class="name"><?= e($name) ?><?php if ($a['is_pro']): ?><span class="qe-pro-tag">PRO</span><?php endif; ?></div>
                <div class="desc"><?= e($desc) ?></div>
                <div class="price">SAR/<?= e($a['unit_type']) ?> <?= number_format((float)$a['unit_price'],2) ?></div>
                <?php if ($manual): ?>
                  <div class="qe-addon-qty">
                    <span><?= t('qe.qty') ?> (<?= e($a['unit_type']) ?>)</span>
                    <input type="number" class="qe-qty-input" name="addon_qty[<?= $a['id'] ?>]" min="0" step="0.01" value="<?= e((string) $qtyValue) ?>" onclick="event.stopPropagation()">
                  </div>
                <?php endif; ?>
              </div>
              <input type="checkbox" id="qe-addon-<?= $a['id'] ?>" name="addons[]" value="<?= $a['id'] ?>" <?= $checked ? 'checked' : '' ?>>
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary"><?= $submitLabel ?></button>
    </div>
  </div>
</form>

<script>
(function() {
  const vatRate = <?= json_encode($vatRate) ?>;
  const areaInput = document.getElementById('qe-area-input');
  const discountInput = document.getElementById('qe-discount-input');
  let selectedRegion = null;
  let selectedFoundation = null;
  let selectedTier = null;
  const selectedAddons = new Map();

  function selectCard(container, card) {
    container.querySelectorAll('.qe-pick-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    card.querySelector('input').checked = true;
  }

  document.querySelectorAll('#qe-regions .qe-pick-card').forEach(card => {
    card.addEventListener('click', () => {
      selectCard(document.getElementById('qe-regions'), card);
      selectedRegion = { price: parseFloat(card.dataset.price), mult: parseFloat(card.dataset.mult) };
      recalc();
    });
  });

  document.querySelectorAll('#qe-foundations .qe-pick-card').forEach(card => {
    card.addEventListener('click', () => {
      selectCard(document.getElementById('qe-foundations'), card);
      selectedFoundation = { price: parseFloat(card.dataset.price) };
      recalc();
    });
  });

  document.querySelectorAll('#qe-tiers .qe-pick-card').forEach(card => {
    card.addEventListener('click', () => {
      selectCard(document.getElementById('qe-tiers'), card);
      selectedTier = { mult: parseFloat(card.dataset.mult) };
      recalc();
    });
  });

  document.querySelectorAll('#qe-addons .qe-addon-card').forEach(card => {
    const checkbox = card.querySelector('input[type="checkbox"]');
    const qtyInput = card.querySelector('.qe-qty-input');
    const mode = card.dataset.mode || 'area';

    function syncAddon() {
      if (!checkbox.checked) { selectedAddons.delete(card.dataset.id); return; }
      const qty = mode === 'manual' ? (parseFloat(qtyInput ? qtyInput.value : 1) || 0) : null;
      selectedAddons.set(card.dataset.id, { price: parseFloat(card.dataset.price), mode: mode, qty: qty });
    }

    checkbox.addEventListener('change', () => {
      card.classList.toggle('selected', checkbox.checked);
      syncAddon();
      recalc();
    });
    if (qtyInput) {
      qtyInput.addEventListener('input', () => { syncAddon(); recalc(); });
    }

    // Seed the JS model from server-rendered prefill (edit form) so the
    // sidebar totals are correct before the user touches anything.
    if (checkbox.checked) {
      syncAddon();
    }
  });

  // Same seeding as above, for the region/foundation/quality-tier radios.
  function initFromChecked(containerId, setter) {
    const container = document.getElementById(containerId);
    const checked = container.querySelector('input:checked');
    if (checked) {
      setter(checked.closest('.qe-pick-card'));
    }
  }
  initFromChecked('qe-regions', card => { selectedRegion = { price: parseFloat(card.dataset.price), mult: parseFloat(card.dataset.mult) }; });
  initFromChecked('qe-foundations', card => { selectedFoundation = { price: parseFloat(card.dataset.price) }; });
  initFromChecked('qe-tiers', card => { selectedTier = { mult: parseFloat(card.dataset.mult) }; });

  areaInput.addEventListener('input', recalc);
  discountInput.addEventListener('input', recalc);

  function recalc() {
    const area = parseFloat(areaInput.value) || 0;
    const discountPct = Math.min(100, Math.max(0, parseFloat(discountInput.value) || 0));

    const base = selectedRegion ? area * selectedRegion.price : 0;
    const foundation = selectedFoundation ? area * selectedFoundation.price : 0;
    let addonsTotal = 0;
    selectedAddons.forEach(a => addonsTotal += a.price * (a.mode === 'manual' ? a.qty : area));

    const regionMult = selectedRegion ? selectedRegion.mult : 1;
    const tierMult = selectedTier ? selectedTier.mult : 1;
    const mult = regionMult * tierMult;
    const subtotal = (base + foundation + addonsTotal) * mult;
    const discountAmt = subtotal * discountPct / 100;
    const taxable = subtotal - discountAmt;
    const vatAmt = taxable * vatRate / 100;
    const total = taxable + vatAmt;
    const perSqm = area > 0 ? total / area : 0;

    document.getElementById('qe-addons-total').textContent = addonsTotal.toFixed(2);
    document.getElementById('qe-foundation-total').textContent = foundation.toFixed(2);
    document.getElementById('qe-region-mult').textContent = regionMult.toFixed(2) + 'x';
    document.getElementById('qe-tier-mult').textContent = tierMult.toFixed(2) + 'x';
    document.getElementById('qe-discount-pct').textContent = discountPct + '%';
    document.getElementById('qe-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('qe-discount-amt').textContent = '-' + discountAmt.toFixed(2);
    document.getElementById('qe-vat-amt').textContent = vatAmt.toFixed(2);
    document.getElementById('qe-total').textContent = total.toFixed(2);
    document.getElementById('qe-per-sqm').textContent = perSqm.toFixed(2);
  }

  recalc();
})();
</script>
