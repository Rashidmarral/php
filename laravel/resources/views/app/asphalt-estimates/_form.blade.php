{{--
  Shared by create.blade.php and edit.blade.php: the project/client/city
  picker, the repeatable road-segment table (same add-row/remove-row JS
  pattern as resources/views/app/estimates/_line-items.blade.php), the
  "Other Costs" fields, and the markup/tax mini-calculator. $segments
  defaults to a single blank row for create; edit() passes the estimate's
  current segments.
--}}
<?php
  $estimate = $estimate ?? [];
  $segments = $segments ?: [[
      'name' => '', 'length_m' => '', 'width_m' => '', 'median_width_m' => 0,
      'subbase_thickness_mm' => 0, 'subbase_unit_cost_per_m3' => 0,
      'base_course_thickness_mm' => 0, 'base_course_unit_cost_per_m3' => 0,
      'asphalt_binder_thickness_mm' => 0, 'asphalt_wearing_thickness_mm' => 0,
      'asphalt_density_kg_per_m3' => 2400, 'asphalt_unit_cost_per_ton' => 0,
  ]];
?>

<form method="post" action="<?= e($formAction) ?>" id="ae-form">
  <?= csrf_field() ?>

  <div class="card" style="margin-bottom:18px;">
    <h3><?= t('user.asphalt_estimates.project_details') ?></h3>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.asphalt_estimates.name') ?></label>
        <input type="text" name="name" maxlength="150" required value="<?= e($estimate['name'] ?? '') ?>" placeholder="<?= e(t('user.asphalt_estimates.name_placeholder')) ?>">
      </div>
      <div class="form-group">
        <label><?= t('common.city') ?></label>
        <select name="city" class="city-select" data-placeholder="<?= e(t('user.settings.city_search_placeholder')) ?>" data-no-results="<?= e(t('user.settings.city_no_results')) ?>">
          <option value=""><?= t('common.select') ?></option>
          <?php foreach (\App\Support\SaudiCities::withExtra($estimate['city'] ?? null) as $city): ?>
            <option value="<?= e($city['en']) ?>" data-ar="<?= e($city['ar']) ?>" <?= ($estimate['city'] ?? '') === $city['en'] ? 'selected' : '' ?>><?= e($city['ar'] !== '' ? $city['en'] . ' / ' . $city['ar'] : $city['en']) ?></option>
          <?php endforeach; ?>
        </select>
        <p class="help-text" style="margin-top:4px;"><?= t('user.asphalt_estimates.city_hint') ?></p>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.quick_estimate.project_optional') ?></label>
        <select name="project_id" class="combo-select" data-placeholder="<?= e(t('common.search_projects_placeholder')) ?>" data-no-results="<?= e(t('common.no_projects_found')) ?>">
          <option value=""><?= t('user.quick_estimate.no_project_linked') ?></option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>" data-ar="<?= e($p['name_ar'] ?? '') ?>" <?= ((int) ($estimate['project_id'] ?? 0) === (int) $p['id']) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= t('user.quick_estimate.client_optional') ?></label>
        <select name="client_id" class="combo-select" data-placeholder="<?= e(t('common.search_clients_placeholder')) ?>" data-no-results="<?= e(t('common.no_clients_found')) ?>">
          <option value=""><?= t('user.quick_estimate.no_client_linked') ?></option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" data-ar="<?= e($c['name_ar'] ?? '') ?>" <?= ((int) ($estimate['client_id'] ?? 0) === (int) $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label><?= t('common.notes') ?></label>
      <textarea name="notes" maxlength="5000"><?= e($estimate['notes'] ?? '') ?></textarea>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h3>🛣️ <?= t('user.asphalt_estimates.segments') ?></h3>
    <p class="help-text" style="margin-top:-4px;"><?= t('user.asphalt_estimates.segments_hint') ?></p>
    <div style="overflow-x:auto;">
    <table class="line-items" id="segments-table">
      <thead><tr>
        <th><?= t('user.asphalt_estimates.col_segment_name') ?></th>
        <th><?= t('user.asphalt_estimates.col_length') ?></th>
        <th><?= t('user.asphalt_estimates.col_width') ?></th>
        <th><?= t('user.asphalt_estimates.col_median') ?></th>
        <th><?= t('user.asphalt_estimates.col_subbase_thickness') ?></th>
        <th><?= t('user.asphalt_estimates.col_subbase_cost') ?></th>
        <th><?= t('user.asphalt_estimates.col_base_thickness') ?></th>
        <th><?= t('user.asphalt_estimates.col_base_cost') ?></th>
        <th><?= t('user.asphalt_estimates.col_binder_thickness') ?></th>
        <th><?= t('user.asphalt_estimates.col_wearing_thickness') ?></th>
        <th><?= t('user.asphalt_estimates.col_density') ?></th>
        <th><?= t('user.asphalt_estimates.col_asphalt_cost') ?></th>
        <th><?= t('user.asphalt_estimates.col_segment_total') ?></th>
        <th></th>
      </tr></thead>
      <tbody id="segments-body">
        <?php foreach ($segments as $row): ?>
        <tr>
          <td><input type="text" name="segment_name[]" maxlength="150" value="<?= e($row['name'] ?? '') ?>" placeholder="<?= e(t('user.asphalt_estimates.segment_name_placeholder')) ?>" class="seg-name"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_length_m[]" value="<?= e((string) ($row['length_m'] ?? '')) ?>" class="seg-length" style="width:90px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_width_m[]" value="<?= e((string) ($row['width_m'] ?? '')) ?>" class="seg-width" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_median_width_m[]" value="<?= e((string) ($row['median_width_m'] ?? 0)) ?>" class="seg-median" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_subbase_thickness_mm[]" value="<?= e((string) ($row['subbase_thickness_mm'] ?? 0)) ?>" class="seg-subbase-t" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_subbase_unit_cost_per_m3[]" value="<?= e((string) ($row['subbase_unit_cost_per_m3'] ?? 0)) ?>" class="seg-subbase-c" style="width:90px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_base_course_thickness_mm[]" value="<?= e((string) ($row['base_course_thickness_mm'] ?? 0)) ?>" class="seg-base-t" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_base_course_unit_cost_per_m3[]" value="<?= e((string) ($row['base_course_unit_cost_per_m3'] ?? 0)) ?>" class="seg-base-c" style="width:90px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_binder_thickness_mm[]" value="<?= e((string) ($row['asphalt_binder_thickness_mm'] ?? 0)) ?>" class="seg-binder-t" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_wearing_thickness_mm[]" value="<?= e((string) ($row['asphalt_wearing_thickness_mm'] ?? 0)) ?>" class="seg-wearing-t" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_density_kg_per_m3[]" value="<?= e((string) ($row['asphalt_density_kg_per_m3'] ?? 2400)) ?>" class="seg-density" style="width:80px;"></td>
          <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_unit_cost_per_ton[]" value="<?= e((string) ($row['asphalt_unit_cost_per_ton'] ?? 0)) ?>" class="seg-asphalt-c" style="width:90px;"></td>
          <td class="seg-total">0.00</td>
          <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <button type="button" id="add-segment-row" class="btn btn-sm btn-outline"><?= t('user.asphalt_estimates.add_segment') ?></button>
  </div>

  <div class="card" style="margin-bottom:18px;">
    <h3>🚜 <?= t('user.asphalt_estimates.other_costs') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.transport_cost_per_ton') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="transport_cost_per_ton" id="ae-transport-rate" value="<?= e((string) ($estimate['transport_cost_per_ton'] ?? 0)) ?>"></div>
      <div class="form-group"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.paver_daily_rate') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="paver_daily_rate" id="ae-paver-rate" value="<?= e((string) ($estimate['paver_daily_rate'] ?? 0)) ?>"></div>
      <div class="form-group"><label><?= t('user.asphalt_estimates.paver_days') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="paver_days" id="ae-paver-days" value="<?= e((string) ($estimate['paver_days'] ?? 0)) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.roller_daily_rate') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="roller_daily_rate" id="ae-roller-rate" value="<?= e((string) ($estimate['roller_daily_rate'] ?? 0)) ?>"></div>
      <div class="form-group"><label><?= t('user.asphalt_estimates.roller_days') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="roller_days" id="ae-roller-days" value="<?= e((string) ($estimate['roller_days'] ?? 0)) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.labor_crew_size') ?></label><input type="number" min="0" step="1" name="labor_crew_size" id="ae-crew-size" value="<?= e((string) ($estimate['labor_crew_size'] ?? 0)) ?>"></div>
      <div class="form-group"><label><?= t('user.asphalt_estimates.labor_days') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="labor_days" id="ae-labor-days" value="<?= e((string) ($estimate['labor_days'] ?? 0)) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.labor_daily_rate_per_worker') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="labor_daily_rate_per_worker" id="ae-labor-rate" value="<?= e((string) ($estimate['labor_daily_rate_per_worker'] ?? 0)) ?>"></div>
      <div class="form-group"><label><?= t('user.asphalt_estimates.labor_food_daily_rate_per_worker') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="labor_food_daily_rate_per_worker" id="ae-food-rate" value="<?= e((string) ($estimate['labor_food_daily_rate_per_worker'] ?? 0)) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.surveyor_fee') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="surveyor_fee" id="ae-surveyor" value="<?= e((string) ($estimate['surveyor_fee'] ?? 0)) ?>"></div>
      <div class="form-group"><label><?= t('user.asphalt_estimates.lab_testing_fee') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="lab_testing_fee" id="ae-lab" value="<?= e((string) ($estimate['lab_testing_fee'] ?? 0)) ?>"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.asphalt_estimates.mobilization_fee') ?></label><input type="number" inputmode="decimal" step="0.01" min="0" name="mobilization_fee" id="ae-mobilization" value="<?= e((string) ($estimate['mobilization_fee'] ?? 0)) ?>"></div>
      <div class="form-group"></div>
    </div>
  </div>

  <div class="card" style="margin-bottom:18px;max-width:420px;">
    <div class="form-row">
      <div class="form-group" style="margin:0;">
        <label><?= t('user.asphalt_estimates.markup_percent') ?></label>
        <input type="number" inputmode="decimal" step="0.01" min="0" max="100" name="markup_percent" id="ae-markup-percent" value="<?= e((string) ($estimate['markup_percent'] ?? 0)) ?>">
      </div>
      <div class="form-group" style="margin:0;">
        <label><?= t('user.asphalt_estimates.tax_rate') ?></label>
        <select name="tax_rate_id" id="ae-tax-rate-select">
          <option value=""><?= t('user.estimates.no_tax') ?></option>
          <?php foreach ($taxRates as $tr): ?>
            <option value="<?= $tr['id'] ?>" data-percent="<?= $tr['rate_percent'] ?>" <?= (string) ($estimate['tax_rate_id'] ?? '') === (string) $tr['id'] ? 'selected' : '' ?>><?= e($tr['name']) ?> (<?= $tr['rate_percent'] ?>%)</option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="breakdown" style="margin-top:12px;font-size:13.5px;">
      <div><span><?= t('user.asphalt_estimates.segments_subtotal') ?></span><span id="ae-segments-subtotal">0.00</span></div>
      <div><span><?= t('user.asphalt_estimates.other_costs_subtotal') ?></span><span id="ae-other-subtotal">0.00</span></div>
      <div><span><?= t('qe.subtotal') ?></span><span id="ae-subtotal">0.00</span></div>
      <div><span><?= t('common.margin') ?></span><span id="ae-markup">0.00</span></div>
      <div><span><?= t('user.asphalt_estimates.tax_rate') ?></span><span id="ae-tax">0.00</span></div>
    </div>
    <div class="total-row" style="margin-top:8px;"><?= t('common.total') ?>: <span id="ae-total">0.00</span> SAR</div>
  </div>

  <button type="submit" class="btn btn-primary"><?= e($submitLabel) ?></button>
</form>

<script>
(function() {
  const body = document.getElementById('segments-body');
  const addBtn = document.getElementById('add-segment-row');
  const markupInput = document.getElementById('ae-markup-percent');
  const taxSelect = document.getElementById('ae-tax-rate-select');
  const otherInputs = ['ae-transport-rate', 'ae-paver-rate', 'ae-paver-days', 'ae-roller-rate', 'ae-roller-days', 'ae-crew-size', 'ae-labor-days', 'ae-labor-rate', 'ae-food-rate', 'ae-surveyor', 'ae-lab', 'ae-mobilization'];

  function rowTemplate() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td><input type="text" name="segment_name[]" maxlength="150" class="seg-name" placeholder="{{ t('user.asphalt_estimates.segment_name_placeholder') }}"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_length_m[]" class="seg-length" style="width:90px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_width_m[]" class="seg-width" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_median_width_m[]" value="0" class="seg-median" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_subbase_thickness_mm[]" value="0" class="seg-subbase-t" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_subbase_unit_cost_per_m3[]" value="0" class="seg-subbase-c" style="width:90px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_base_course_thickness_mm[]" value="0" class="seg-base-t" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_base_course_unit_cost_per_m3[]" value="0" class="seg-base-c" style="width:90px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_binder_thickness_mm[]" value="0" class="seg-binder-t" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_wearing_thickness_mm[]" value="0" class="seg-wearing-t" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_density_kg_per_m3[]" value="2400" class="seg-density" style="width:80px;"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" name="segment_asphalt_unit_cost_per_ton[]" value="0" class="seg-asphalt-c" style="width:90px;"></td>
      <td class="seg-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function num(tr, cls) {
    const el = tr.querySelector('.' + cls);
    return el ? (parseFloat(el.value) || 0) : 0;
  }

  // Mirrors App\Support\AsphaltEstimateCalc exactly — a live client-side
  // preview only; the server recomputes and persists the authoritative
  // numbers on submit.
  function recalc() {
    let segmentsSubtotal = 0;
    let totalTons = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const length = num(tr, 'seg-length');
      const width = num(tr, 'seg-width');
      const median = num(tr, 'seg-median');
      const netWidth = Math.max(0, width - median);
      const area = length * netWidth;
      const subbaseVol = area * (num(tr, 'seg-subbase-t') / 1000);
      const baseVol = area * (num(tr, 'seg-base-t') / 1000);
      const asphaltVol = area * ((num(tr, 'seg-binder-t') + num(tr, 'seg-wearing-t')) / 1000);
      const density = num(tr, 'seg-density') || 2400;
      const tons = asphaltVol * (density / 1000);
      const subbaseCost = subbaseVol * num(tr, 'seg-subbase-c');
      const baseCost = baseVol * num(tr, 'seg-base-c');
      const asphaltCost = tons * num(tr, 'seg-asphalt-c');
      const segmentTotal = subbaseCost + baseCost + asphaltCost;
      tr.querySelector('.seg-total').textContent = segmentTotal.toFixed(2);
      segmentsSubtotal += segmentTotal;
      totalTons += tons;
    });

    const transportCost = totalTons * (parseFloat(document.getElementById('ae-transport-rate').value) || 0);
    const paverCost = (parseFloat(document.getElementById('ae-paver-rate').value) || 0) * (parseFloat(document.getElementById('ae-paver-days').value) || 0);
    const rollerCost = (parseFloat(document.getElementById('ae-roller-rate').value) || 0) * (parseFloat(document.getElementById('ae-roller-days').value) || 0);
    const workerDays = (parseFloat(document.getElementById('ae-crew-size').value) || 0) * (parseFloat(document.getElementById('ae-labor-days').value) || 0);
    const laborCost = workerDays * (parseFloat(document.getElementById('ae-labor-rate').value) || 0);
    const laborFoodCost = workerDays * (parseFloat(document.getElementById('ae-food-rate').value) || 0);
    const surveyor = parseFloat(document.getElementById('ae-surveyor').value) || 0;
    const lab = parseFloat(document.getElementById('ae-lab').value) || 0;
    const mobilization = parseFloat(document.getElementById('ae-mobilization').value) || 0;
    const otherSubtotal = transportCost + paverCost + rollerCost + laborCost + laborFoodCost + surveyor + lab + mobilization;

    const subtotal = segmentsSubtotal + otherSubtotal;
    const markupPercent = parseFloat(markupInput.value) || 0;
    const markupAmount = subtotal * markupPercent / 100;
    const sellSubtotal = subtotal + markupAmount;
    const taxOption = taxSelect.options[taxSelect.selectedIndex];
    const taxPercent = taxOption ? (parseFloat(taxOption.dataset.percent) || 0) : 0;
    const taxAmount = sellSubtotal * taxPercent / 100;
    const total = sellSubtotal + taxAmount;

    document.getElementById('ae-segments-subtotal').textContent = segmentsSubtotal.toFixed(2);
    document.getElementById('ae-other-subtotal').textContent = otherSubtotal.toFixed(2);
    document.getElementById('ae-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('ae-markup').textContent = markupAmount.toFixed(2);
    document.getElementById('ae-tax').textContent = taxAmount.toFixed(2);
    document.getElementById('ae-total').textContent = total.toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
      recalc();
    }
  });
  markupInput.addEventListener('input', recalc);
  taxSelect.addEventListener('change', recalc);
  otherInputs.forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('input', recalc);
  });

  recalc();
})();
</script>
