<?php use App\Core\View; use App\Core\Csrf; use App\Core\Lang; ?>
<div class="page-head">
  <div>
    <h1>⚡ <?= t('qe.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.quick_estimate.intro_hint') ?></p>
  </div>
</div>

<form method="post" action="/app/quick-estimate" id="qe-form">
  <?= Csrf::field() ?>
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

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">✉ <?= t('qe.generate') ?></button>
    </div>

    <!-- Main form -->
    <div>
      <div class="card" style="margin-bottom:18px;">
        <h3><?= t('qe.project_details') ?></h3>
        <div class="form-row">
          <div class="form-group">
            <label><?= t('qe.project_name') ?></label>
            <input type="text" name="project_name" placeholder="<?= t('qe.project_name') ?>">
          </div>
          <div class="form-group">
            <label><?= t('user.quick_estimate.client_optional') ?></label>
            <select name="client_id">
              <option value=""><?= t('user.quick_estimate.no_client_linked') ?></option>
              <?php foreach ($clients as $c): ?>
                <option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px;">
        <h3>📍 <?= t('qe.region') ?></h3>
        <div class="qe-pick-grid" id="qe-regions">
          <?php foreach ($regions as $r): $name = Lang::locale() === 'ar' ? $r['name_ar'] : $r['name_en']; ?>
            <label class="qe-pick-card" data-id="<?= $r['id'] ?>" data-price="<?= $r['price_per_sqm'] ?>" data-mult="<?= $r['multiplier'] ?>">
              <input type="radio" name="region_id" value="<?= $r['id'] ?>">
              <div class="name"><?= View::e($name) ?></div>
              <div class="price">SAR <?= number_format((float)$r['price_per_sqm'],2) ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <h3>🧱 <?= t('qe.foundation_type') ?></h3>
        <div class="qe-pick-grid" id="qe-foundations" style="grid-template-columns:repeat(2,1fr);">
          <?php foreach ($foundations as $f): $name = Lang::locale() === 'ar' ? $f['name_ar'] : $f['name_en']; $desc = Lang::locale() === 'ar' ? $f['description_ar'] : $f['description_en']; ?>
            <label class="qe-pick-card" data-id="<?= $f['id'] ?>" data-price="<?= $f['price_per_sqm'] ?>">
              <input type="radio" name="foundation_id" value="<?= $f['id'] ?>">
              <div class="name"><?= View::e($name) ?></div>
              <div class="desc"><?= View::e($desc) ?></div>
              <div class="price">SAR/m² <?= number_format((float)$f['price_per_sqm'],2) ?></div>
            </label>
          <?php endforeach; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label><?= t('qe.discount') ?> (%)</label>
            <input type="number" name="discount_percent" id="qe-discount-input" min="0" max="100" value="0">
          </div>
          <div class="form-group">
            <label><?= t('qe.total_area') ?> (m²)</label>
            <input type="number" name="total_area" id="qe-area-input" min="0" value="0" required>
          </div>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px;">
        <h3>🔧 <?= t('qe.addons') ?></h3>
        <p class="help-text" style="margin-top:-8px;"><?= t('qe.addons_hint') ?></p>
        <div class="qe-addon-grid" id="qe-addons">
          <?php foreach ($addons as $a): $name = Lang::locale() === 'ar' ? $a['name_ar'] : $a['name_en']; $desc = Lang::locale() === 'ar' ? $a['description_ar'] : $a['description_en']; ?>
            <label class="qe-addon-card" data-id="<?= $a['id'] ?>" data-price="<?= $a['unit_price'] ?>">
              <div>
                <div class="name"><?= View::e($name) ?><?php if ($a['is_pro']): ?><span class="qe-pro-tag">PRO</span><?php endif; ?></div>
                <div class="desc"><?= View::e($desc) ?></div>
                <div class="price">SAR/<?= View::e($a['unit_type']) ?> <?= number_format((float)$a['unit_price'],2) ?></div>
              </div>
              <input type="checkbox" name="addons[]" value="<?= $a['id'] ?>">
            </label>
          <?php endforeach; ?>
        </div>
      </div>

      <button type="submit" class="btn btn-primary">✉ <?= t('qe.generate') ?></button>
    </div>
  </div>
</form>

<div class="card" style="margin-top:28px;">
  <h3><?= t('user.quick_estimate.my_quick_estimates') ?></h3>
  <?php if (empty($quotes)): ?>
    <p class="help-text"><?= t('user.quick_estimate.none_generated') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('user.quick_estimate.project_col') ?></th><th><?= t('common.client') ?></th><th><?= t('user.quick_estimate.area_col') ?></th><th><?= t('common.total') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($quotes as $q): ?>
          <tr>
            <td><?= View::e($q['created_at']) ?></td>
            <td><a href="/app/quick-estimate/<?= $q['id'] ?>"><?= View::e($q['project_name'] ?: ('Quick Estimate #' . $q['id'])) ?></a></td>
            <td><?= View::e($q['client_name'] ?? '—') ?></td>
            <td><?= View::e($q['total_area']) ?> m²</td>
            <td><?= View::money((float)$q['total']) ?></td>
            <td><a href="/app/quick-estimate/<?= $q['id'] ?>" class="btn btn-outline btn-sm"><?= t('common.view') ?></a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<script>
(function() {
  const vatRate = <?= json_encode($vatRate) ?>;
  const areaInput = document.getElementById('qe-area-input');
  const discountInput = document.getElementById('qe-discount-input');
  let selectedRegion = null;
  let selectedFoundation = null;
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

  document.querySelectorAll('#qe-addons .qe-addon-card').forEach(card => {
    const checkbox = card.querySelector('input');
    checkbox.addEventListener('change', () => {
      card.classList.toggle('selected', checkbox.checked);
      if (checkbox.checked) {
        selectedAddons.set(card.dataset.id, parseFloat(card.dataset.price));
      } else {
        selectedAddons.delete(card.dataset.id);
      }
      recalc();
    });
  });

  areaInput.addEventListener('input', recalc);
  discountInput.addEventListener('input', recalc);

  function recalc() {
    const area = parseFloat(areaInput.value) || 0;
    const discountPct = Math.min(100, Math.max(0, parseFloat(discountInput.value) || 0));

    const base = selectedRegion ? area * selectedRegion.price : 0;
    const foundation = selectedFoundation ? area * selectedFoundation.price : 0;
    let addonsTotal = 0;
    selectedAddons.forEach(price => addonsTotal += price * area);

    const mult = selectedRegion ? selectedRegion.mult : 1;
    const subtotal = (base + foundation + addonsTotal) * mult;
    const discountAmt = subtotal * discountPct / 100;
    const taxable = subtotal - discountAmt;
    const vatAmt = taxable * vatRate / 100;
    const total = taxable + vatAmt;
    const perSqm = area > 0 ? total / area : 0;

    document.getElementById('qe-addons-total').textContent = addonsTotal.toFixed(2);
    document.getElementById('qe-foundation-total').textContent = foundation.toFixed(2);
    document.getElementById('qe-region-mult').textContent = mult.toFixed(2) + 'x';
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
