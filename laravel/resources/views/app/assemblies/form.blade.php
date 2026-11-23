@extends('layouts.app')

{{--
  Shared create/edit form for an Assembly: its own name/base_uom/description
  fields, then a nested items table with client-side add-row/remove-row JS
  before a single submit — same array-field shape (item_description[],
  item_qty_per_unit[], ...) that AssemblyController::itemsFromInput() parses,
  mirroring how resources/views/app/estimates/_line-items.blade.php builds
  and submits its own item_*[] rows.
--}}
@php
  $rows = $items ?: [[
      'description' => '',
      'description_ar' => '',
      'item_type' => 'material',
      'uom' => '',
      'qty_per_unit' => 1,
      'unit_cost' => 0,
      'cost_code_id' => null,
  ]];
@endphp

@section('content')
<div class="page-head">
  <h1>{{ $assembly ? t('user.assemblies.edit_title') : t('user.assemblies.new_title') }}</h1>
  <a href="/app/assemblies" class="btn btn-light">{{ t('user.assemblies.back_to_assemblies') }}</a>
</div>

<form method="post" action="{{ $assembly ? '/app/assemblies/' . $assembly['id'] : '/app/assemblies' }}" class="card">
  @csrf
  <div class="form-row">
    <div class="form-group"><label>{{ t('common.name_en') }}</label><input type="text" name="name" required maxlength="150" value="{{ $assembly['name'] ?? '' }}" placeholder="e.g. Standard Interior Wall"></div>
    <div class="form-group"><label>{{ t('common.name_ar') }}</label><input type="text" name="name_ar" dir="rtl" maxlength="150" value="{{ $assembly['name_ar'] ?? '' }}" placeholder="الاسم بالعربية"></div>
  </div>
  <div class="form-row">
    <div class="form-group">
      <label>{{ t('user.assemblies.base_uom') }}</label>
      <input type="text" name="base_uom" required list="assembly-uom-options" maxlength="20" value="{{ $assembly['base_uom'] ?? '' }}" placeholder="e.g. lm, sqm, each">
      <datalist id="assembly-uom-options">
        @foreach ($units as $u)<option value="{{ $u['code'] }}">{{ $u['name'] }}</option>@endforeach
      </datalist>
      <p class="help-text">{{ t('user.assemblies.base_uom_hint') }}</p>
    </div>
    <div class="form-group">
      <label>{{ t('common.status') }}</label>
      <select name="is_active">
        <option value="1" {{ ($assembly['is_active'] ?? true) ? 'selected' : '' }}>{{ t('common.active') }}</option>
        <option value="0" {{ !($assembly['is_active'] ?? true) ? 'selected' : '' }}>{{ t('common.inactive') }}</option>
      </select>
    </div>
  </div>
  <div class="form-group"><label>{{ t('common.description') }}</label><input type="text" name="description" maxlength="255" value="{{ $assembly['description'] ?? '' }}"></div>

  <label>{{ t('user.assemblies.items_label') }}</label>
  <p class="help-text" style="margin-top:-4px;">{{ t('user.assemblies.items_hint') }}</p>
  <div style="overflow-x:auto;">
  <table class="line-items" id="assembly-items-table">
    <thead><tr>
      <th style="width:22%">{{ t('common.description_en') }}</th>
      <th style="width:16%">{{ t('common.description_ar') }}</th>
      <th style="width:12%">{{ t('common.type') }}</th>
      <th style="width:9%">UOM</th>
      <th style="width:12%">{{ t('user.assemblies.qty_per_unit') }}</th>
      <th style="width:10%">{{ t('common.unit_cost') }} (SAR)</th>
      <th style="width:14%">{{ t('user.cost_codes.column') }}</th>
      <th></th>
    </tr></thead>
    <tbody id="assembly-items-body">
      @foreach ($rows as $row)
      <tr>
        <td><input type="text" name="item_description[]" maxlength="255" value="{{ $row['description'] ?? '' }}" placeholder="e.g. 2x4 stud"></td>
        <td><input type="text" name="item_description_ar[]" dir="rtl" maxlength="255" value="{{ $row['description_ar'] ?? '' }}" placeholder="الوصف بالعربية"></td>
        <td>
          <select name="item_type[]">
            @foreach ($itemTypes as $val)
              <option value="{{ $val }}" {{ ($row['item_type'] ?? 'material') === $val ? 'selected' : '' }}>{{ t('user.assemblies.item_type_' . $val) }}</option>
            @endforeach
          </select>
        </td>
        <td>
          <select name="item_uom[]">
            @foreach ($units as $u)<option value="{{ $u['code'] }}" {{ ($row['uom'] ?? '') === $u['code'] ? 'selected' : '' }}>{{ $u['code'] }}</option>@endforeach
            <option value="each" {{ ($row['uom'] ?? '') === 'each' ? 'selected' : '' }}>each</option>
          </select>
        </td>
        <td><input type="number" inputmode="decimal" step="0.0001" min="0.01" max="999999.99" name="item_qty_per_unit[]" value="{{ $row['qty_per_unit'] ?? 1 }}" class="qty-per-unit"></td>
        <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999.99" name="item_cost[]" value="{{ $row['unit_cost'] ?? 0 }}" class="cost"></td>
        <td>@include('app.partials.cost-code-select', ['costCodeDivisions' => $costCodeDivisions, 'name' => 'item_cost_code[]', 'selected' => $row['cost_code_id'] ?? null])</td>
        <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>
      </tr>
      @endforeach
    </tbody>
  </table>
  </div>
  <div style="margin-top:8px;">
    <button type="button" id="assembly-add-row" class="btn btn-sm btn-outline">{{ t('user.assemblies.add_item') }}</button>
  </div>

  <button type="submit" class="btn btn-primary" style="margin-top:20px;">{{ $assembly ? t('common.save_changes') : t('user.assemblies.add_assembly') }}</button>
</form>

<script>
(function() {
  const body = document.getElementById('assembly-items-body');
  const addBtn = document.getElementById('assembly-add-row');
  const unitOptions = {!! json_encode(array_column($units, 'code')) !!};
  const itemTypes = {!! json_encode($itemTypes) !!};
  const itemTypeLabels = {!! json_encode(array_combine($itemTypes, array_map(fn ($v) => t('user.assemblies.item_type_' . $v), $itemTypes))) !!};
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
    const typeOpts = itemTypes.map(v => `<option value="${v}">${itemTypeLabels[v]}</option>`).join('');
    tr.innerHTML = `
      <td><input type="text" name="item_description[]" maxlength="255" placeholder="Description"></td>
      <td><input type="text" name="item_description_ar[]" dir="rtl" maxlength="255" placeholder="الوصف بالعربية"></td>
      <td><select name="item_type[]">${typeOpts}</select></td>
      <td><select name="item_uom[]">${unitOpts}</select></td>
      <td><input type="number" inputmode="decimal" step="0.0001" min="0.01" max="999999.99" name="item_qty_per_unit[]" value="1" class="qty-per-unit"></td>
      <td><input type="number" inputmode="decimal" step="0.01" min="0" max="9999999.99" name="item_cost[]" value="0" class="cost"></td>
      <td><select name="item_cost_code[]" class="cost-code-select">${costCodeOptionsHtml()}</select></td>
      <td><button type="button" class="btn btn-sm btn-light remove-row">✕</button></td>`;
    return tr;
  }

  addBtn.addEventListener('click', () => { body.appendChild(rowTemplate()); });
  body.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-row')) {
      if (body.querySelectorAll('tr').length > 1) e.target.closest('tr').remove();
    }
  });
})();
</script>
@endsection
