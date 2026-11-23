{{--
  Shared by form.blade.php (create) and edit.blade.php (edit): the line-items
  table (with add-row/remove-row/pull-from-library) and the markup%/tax mini
  calculator, plus their JS. $prefillItems defaults to a single blank row for
  create; edit() passes the estimate's current items via prefillItemRows().
--}}
@php
  $rows = $prefillItems ?: [[
      'item_section' => '',
      'description' => '',
      'description_ar' => '',
      'item_type' => 'material',
      'qty' => 1,
      'uom' => '',
      'unit_cost' => 0,
      'is_optional' => false,
      'cost_code_id' => null,
  ]];
  $itemTypes = ['material' => 'Material', 'labor' => 'Labor', 'equipment' => 'Equipment', 'subcontractor' => 'Subcontractor', 'other' => 'Other'];
@endphp

<label>{{ t('user.invoices.line_items') }}</label>
<p class="help-text" style="margin-top:-4px;">Add a section name on a line's first item to start a new numbered category (e.g. "Site Prep &amp; Foundations") — leave it blank on the rest of that section's items.</p>
<div style="overflow-x:auto;">
<table class="line-items" id="items-table">
  <thead><tr>
    <th style="width:16%">Section (optional)</th>
    <th style="width:22%">{{ t('common.description_en') }}</th>
    <th style="width:16%">{{ t('common.description_ar') }}</th>
    <th style="width:11%">Type</th>
    <th style="width:8%">{{ t('common.qty') }}</th>
    <th style="width:9%">UOM</th>
    <th style="width:10%">{{ t('common.unit_cost') }} (SAR)</th>
    <th style="width:10%">{{ t('user.invoices.line_total') }}</th>
    <th style="width:9%">{{ t('user.estimates.optional_addon') }}</th>
    <th style="width:14%">{{ t('user.cost_codes.column') }}</th>
    <th></th>
  </tr></thead>
  <tbody id="items-body">
    @foreach ($rows as $row)
    <tr>
      <td><input type="text" name="item_section[]" maxlength="150" value="{{ $row['item_section'] ?? '' }}" placeholder="e.g. 1.0 Site Prep"></td>
      <td><input type="text" name="item_description[]" maxlength="255" value="{{ $row['description'] ?? '' }}" placeholder="e.g. Excavation and site clearance"></td>
      <td><input type="text" name="item_description_ar[]" dir="rtl" maxlength="255" value="{{ $row['description_ar'] ?? '' }}" placeholder="الوصف بالعربية"></td>
      <td>
        <select name="item_type[]">
          @foreach ($itemTypes as $val => $label)
            <option value="{{ $val }}" {{ ($row['item_type'] ?? 'material') === $val ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0.01" max="999999.99" name="item_qty[]" value="{{ $row['qty'] ?? 1 }}" class="qty"></td>
      <td>
        <select name="item_uom[]">
          @foreach ($units as $u)<option value="{{ $u['code'] }}" {{ ($row['uom'] ?? '') === $u['code'] ? 'selected' : '' }}>{{ $u['code'] }}</option>@endforeach
          <option value="each" {{ ($row['uom'] ?? '') === 'each' ? 'selected' : '' }}>each</option>
        </select>
      </td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999.99" name="item_cost[]" value="{{ $row['unit_cost'] ?? 0 }}" class="cost"></td>
      <td class="line-total">0.00</td>
      {{-- An unchecked checkbox is simply omitted from the POST body, which
           would desync item_optional[]'s index from every other item_*[]
           array (always present, one entry per row) once any earlier row is
           left unchecked. The hidden field is always submitted and the
           checkbox only overwrites it via JS, keeping exactly one entry per
           row in the same order as the other fields. --}}
      <td style="text-align:center;">
        <input type="hidden" name="item_optional[]" value="{{ !empty($row['is_optional']) ? '1' : '0' }}" class="optional-flag">
        <input type="checkbox" class="optional-checkbox" {{ !empty($row['is_optional']) ? 'checked' : '' }} title="{{ t('user.estimates.optional_addon_hint') }}">
      </td>
      <td>@include('app.partials.cost-code-select', ['costCodeDivisions' => $costCodeDivisions, 'name' => 'item_cost_code[]', 'selected' => $row['cost_code_id'] ?? null])</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
    </tr>
    @endforeach
  </tbody>
</table>
</div>
<p class="help-text" style="margin-top:6px;">{{ t('user.estimates.optional_addon_hint') }}</p>
<div style="display:flex;gap:8px;margin-top:8px;">
  <button type="button" id="add-row" class="btn btn-sm btn-outline">{{ t('user.invoices.add_line_item') }}</button>
  <button type="button" id="open-library-picker" class="btn btn-sm btn-outline">{{ t('user.invoices.pull_from_library') }}</button>
  <button type="button" id="open-assembly-picker" class="btn btn-sm btn-outline">{{ t('user.invoices.insert_assembly') }}</button>
</div>

<div class="card" style="margin-top:20px;background:var(--bg);max-width:420px;margin-inline-start:auto;">
  <div class="form-row">
    <div class="form-group" style="margin:0;">
      <label>Profit margin %</label>
      <input type="number" inputmode="decimal" step="0.01" min="0" max="100" name="markup_percent" id="markup-percent" value="{{ $markupPercent }}">
    </div>
    <div class="form-group" style="margin:0;">
      <label>Tax rate</label>
      <select name="tax_rate_id" id="tax-rate-select">
        <option value="">No tax</option>
        @foreach ($taxRates as $tr)
          <option value="{{ $tr['id'] }}" data-percent="{{ $tr['rate_percent'] }}" {{ (string)($taxRateId ?? '') === (string)$tr['id'] ? 'selected' : '' }}>{{ $tr['name'] }} ({{ $tr['rate_percent'] }}%)</option>
        @endforeach
      </select>
    </div>
  </div>
  <div class="breakdown" style="margin-top:12px;font-size:13.5px;">
    <div><span>Cost subtotal</span><span id="calc-subtotal">0.00</span></div>
    <div><span>Margin</span><span id="calc-markup">0.00</span></div>
    <div><span>Tax</span><span id="calc-tax">0.00</span></div>
  </div>
  <div class="total-row" style="margin-top:8px;">Total: <span id="grand-total">0.00</span> SAR</div>
</div>

@include('app.partials.library-picker')
@include('app.partials.assembly-picker')

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');
  const markupInput = document.getElementById('markup-percent');
  const taxSelect = document.getElementById('tax-rate-select');
  const unitOptions = {!! json_encode(array_column($units, 'code')) !!};
  const optionalHint = {!! json_encode(t('user.estimates.optional_addon_hint')) !!};
  const costCodeDivisions = {!! json_encode($costCodeDivisions) !!};
  const costCodeLocale = {!! json_encode(app()->getLocale()) !!};
  const costCodeNoneLabel = {!! json_encode(t('user.cost_codes.none')) !!};

  function costCodeLabel(row) {
    return (costCodeLocale === 'ar' && row.description_ar) ? row.description_ar : row.description;
  }

  function costCodeOptionsHtml() {
    let html = `<option value="">${costCodeNoneLabel}</option>`;
    costCodeDivisions.forEach((division) => {
      html += `<optgroup label="${division.code} ${costCodeLabel(division)}">`;
      html += `<option value="${division.id}">${division.code} — ${costCodeLabel(division)}</option>`;
      (division.children || []).forEach((child) => {
        html += `<option value="${child.id}">&nbsp;&nbsp;${child.code} — ${costCodeLabel(child)}</option>`;
      });
      html += '</optgroup>';
    });
    return html;
  }

  function rowTemplate() {
    const tr = document.createElement('tr');
    const unitOpts = unitOptions.map(u => `<option value="${u}">${u}</option>`).join('') + '<option value="each">each</option>';
    tr.innerHTML = `
      <td><input type="text" name="item_section[]" maxlength="150" placeholder="optional"></td>
      <td><input type="text" name="item_description[]" maxlength="255" placeholder="Description"></td>
      <td><input type="text" name="item_description_ar[]" dir="rtl" maxlength="255" placeholder="الوصف بالعربية"></td>
      <td>
        <select name="item_type[]">
          <option value="material">Material</option>
          <option value="labor">Labor</option>
          <option value="equipment">Equipment</option>
          <option value="subcontractor">Subcontractor</option>
          <option value="other">Other</option>
        </select>
      </td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0.01" max="999999.99" name="item_qty[]" value="1" class="qty"></td>
      <td><select name="item_uom[]">${unitOpts}</select></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999.99" name="item_cost[]" value="0" class="cost"></td>
      <td class="line-total">0.00</td>
      <td style="text-align:center;">
        <input type="hidden" name="item_optional[]" value="0" class="optional-flag">
        <input type="checkbox" class="optional-checkbox" title="${optionalHint}">
      </td>
      <td><select name="item_cost_code[]" class="cost-code-select">${costCodeOptionsHtml()}</select></td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function recalc() {
    // Optional add-ons keep their own real line total but never inflate the
    // mini-calculator's Cost subtotal — mirrors store()/update()'s server-side
    // rule so this preview never diverges from what gets saved.
    let subtotal = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      const checkbox = tr.querySelector('.optional-checkbox');
      const flag = tr.querySelector('.optional-flag');
      flag.value = checkbox.checked ? '1' : '0';
      if (!checkbox.checked) {
        subtotal += lineTotal;
      }
    });
    const markupPercent = parseFloat(markupInput.value) || 0;
    const markupAmount = subtotal * markupPercent / 100;
    const sellSubtotal = subtotal + markupAmount;
    const taxOption = taxSelect.options[taxSelect.selectedIndex];
    const taxPercent = taxOption ? parseFloat(taxOption.dataset.percent) || 0 : 0;
    const taxAmount = sellSubtotal * taxPercent / 100;
    const total = sellSubtotal + taxAmount;

    document.getElementById('calc-subtotal').textContent = subtotal.toFixed(2);
    document.getElementById('calc-markup').textContent = markupAmount.toFixed(2);
    document.getElementById('calc-tax').textContent = taxAmount.toFixed(2);
    grandTotal.textContent = total.toFixed(2);
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); recalc(); });
  body.addEventListener('input', recalc);
  body.addEventListener('change', recalc);
  markupInput.addEventListener('input', recalc);
  taxSelect.addEventListener('change', recalc);
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
      recalc();
    }
  });

  document.addEventListener('library-item-picked', (e) => {
    const rows = body.querySelectorAll('tr');
    const firstRow = rows[0];
    const firstEmpty = firstRow && !firstRow.querySelector('[name="item_description[]"]').value;
    const tr = firstEmpty ? firstRow : rowTemplate();
    if (!firstEmpty) body.appendChild(tr);
    tr.querySelector('[name="item_description[]"]').value = e.detail.description;
    tr.querySelector('[name="item_description_ar[]"]').value = e.detail.descriptionAr || '';
    tr.querySelector('.cost').value = e.detail.unitCost;
    recalc();
  });

  // Ensures a <select> (UOM or item type) has an option for `value` before
  // it's assigned — an assembly item's uom/cost-code can be anything a
  // company typed, not only what this estimate's own dropdowns list.
  function ensureOption(select, value) {
    if (!value) return;
    const exists = Array.from(select.options).some(o => o.value === String(value));
    if (!exists) {
      const opt = document.createElement('option');
      opt.value = value;
      opt.textContent = value;
      select.appendChild(opt);
    }
  }

  // Appends one new row built from an already-scaled assembly item (see
  // app.partials.assembly-picker's `assembly-items-picked` event) by reusing
  // the same rowTemplate() every other row on this table comes from — the
  // row is then an ordinary, fully editable/removable line item, no
  // different from one typed in by hand.
  function addAssemblyItemRow(item) {
    const tr = rowTemplate();
    body.appendChild(tr);
    tr.querySelector('[name="item_description[]"]').value = item.description;
    tr.querySelector('[name="item_description_ar[]"]').value = item.descriptionAr || '';
    const typeSelect = tr.querySelector('[name="item_type[]"]');
    ensureOption(typeSelect, item.itemType);
    typeSelect.value = item.itemType;
    const uomSelect = tr.querySelector('[name="item_uom[]"]');
    ensureOption(uomSelect, item.uom);
    uomSelect.value = item.uom;
    tr.querySelector('.qty').value = item.qty;
    tr.querySelector('.cost').value = item.unitCost;
    if (item.costCodeId) {
      const costCodeSelect = tr.querySelector('.cost-code-select');
      if (costCodeSelect) costCodeSelect.value = item.costCodeId;
    }
  }

  document.addEventListener('assembly-items-picked', (e) => {
    (e.detail.items || []).forEach(addAssemblyItemRow);
    recalc();
  });

  recalc();
})();
</script>
