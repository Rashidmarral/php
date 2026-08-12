@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.estimates.new_title') }}</h1>
  <a href="{{ url('/app/estimates') }}" class="btn btn-light">{{ t('user.estimates.back_to_estimates') }}</a>
</div>

<form method="post" action="{{ url('/app/estimates') }}" class="card" style="max-width:1080px;">
  @csrf
  <div class="form-row">
    <div class="form-group"><label>{{ t('user.estimates.title_en') }}</label><input type="text" name="title" required placeholder="e.g. Villa Renovation Estimate"></div>
    <div class="form-group"><label>{{ t('user.estimates.title_ar') }}</label><input type="text" name="title_ar" dir="rtl" placeholder="عنوان التسعيرة بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>{{ t('common.client') }}</label>
      <select name="client_id">
        <option value="">{{ t('user.projects.no_client') }}</option>
        @foreach ($clients as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach
      </select>
    </div>
    <div class="form-group">
      <label>{{ t('user.estimates.link_project_optional') }}</label>
      <select name="project_id">
        <option value="">{{ t('user.invoices.no_project') }}</option>
        @foreach ($projects as $p)<option value="{{ $p['id'] }}">{{ $p['name'] }}</option>@endforeach
      </select>
    </div>
  </div>

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
      <th></th>
    </tr></thead>
    <tbody id="items-body">
      <tr>
        <td><input type="text" name="item_section[]" placeholder="e.g. 1.0 Site Prep"></td>
        <td><input type="text" name="item_description[]" placeholder="e.g. Excavation and site clearance"></td>
        <td><input type="text" name="item_description_ar[]" dir="rtl" placeholder="الوصف بالعربية"></td>
        <td>
          <select name="item_type[]">
            <option value="material">Material</option>
            <option value="labor">Labor</option>
            <option value="equipment">Equipment</option>
            <option value="subcontractor">Subcontractor</option>
            <option value="other">Other</option>
          </select>
        </td>
        <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
        <td>
          <select name="item_uom[]">
            @foreach ($units as $u)<option value="{{ $u['code'] }}">{{ $u['code'] }}</option>@endforeach
            <option value="each">each</option>
          </select>
        </td>
        <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
        <td class="line-total">0.00</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
    </tbody>
  </table>
  </div>
  <div style="display:flex;gap:8px;margin-top:8px;">
    <button type="button" id="add-row" class="btn btn-sm btn-outline">{{ t('user.invoices.add_line_item') }}</button>
    <button type="button" id="open-library-picker" class="btn btn-sm btn-outline">{{ t('user.invoices.pull_from_library') }}</button>
  </div>

  <div class="card" style="margin-top:20px;background:var(--bg);max-width:420px;margin-inline-start:auto;">
    <div class="form-row">
      <div class="form-group" style="margin:0;">
        <label>Profit margin %</label>
        <input type="number" step="0.01" min="0" max="100" name="markup_percent" id="markup-percent" value="{{ $defaultMarkupPercent }}">
      </div>
      <div class="form-group" style="margin:0;">
        <label>Tax rate</label>
        <select name="tax_rate_id" id="tax-rate-select">
          <option value="">No tax</option>
          @foreach ($taxRates as $tr)
            <option value="{{ $tr['id'] }}" data-percent="{{ $tr['rate_percent'] }}" {{ (string)($defaultTaxRateId ?? '') === (string)$tr['id'] ? 'selected' : '' }}>{{ $tr['name'] }} ({{ $tr['rate_percent'] }}%)</option>
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

  <button type="submit" class="btn btn-primary" style="margin-top:16px;">{{ t('user.estimates.create_estimate') }}</button>
</form>

@include('app.partials.library-picker')

<script>
(function() {
  const body = document.getElementById('items-body');
  const addBtn = document.getElementById('add-row');
  const grandTotal = document.getElementById('grand-total');
  const markupInput = document.getElementById('markup-percent');
  const taxSelect = document.getElementById('tax-rate-select');
  const unitOptions = {!! json_encode(array_column($units, 'code')) !!};

  function rowTemplate() {
    const tr = document.createElement('tr');
    const unitOpts = unitOptions.map(u => `<option value="${u}">${u}</option>`).join('') + '<option value="each">each</option>';
    tr.innerHTML = `
      <td><input type="text" name="item_section[]" placeholder="optional"></td>
      <td><input type="text" name="item_description[]" placeholder="Description"></td>
      <td><input type="text" name="item_description_ar[]" dir="rtl" placeholder="الوصف بالعربية"></td>
      <td>
        <select name="item_type[]">
          <option value="material">Material</option>
          <option value="labor">Labor</option>
          <option value="equipment">Equipment</option>
          <option value="subcontractor">Subcontractor</option>
          <option value="other">Other</option>
        </select>
      </td>
      <td><input type="number" step="0.01" name="item_qty[]" value="1" class="qty"></td>
      <td><select name="item_uom[]">${unitOpts}</select></td>
      <td><input type="number" step="0.01" name="item_cost[]" value="0" class="cost"></td>
      <td class="line-total">0.00</td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  function recalc() {
    let subtotal = 0;
    body.querySelectorAll('tr').forEach(tr => {
      const qty = parseFloat(tr.querySelector('.qty').value) || 0;
      const cost = parseFloat(tr.querySelector('.cost').value) || 0;
      const lineTotal = qty * cost;
      tr.querySelector('.line-total').textContent = lineTotal.toFixed(2);
      subtotal += lineTotal;
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

  recalc();
})();
</script>

@endsection
