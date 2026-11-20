{{--
  Shared optional cost-code <select>, grouped by division with its sub-codes
  nested underneath. Include with:
    @include('app.partials.cost-code-select', ['costCodeDivisions' => $costCodeDivisions, 'name' => 'cost_code_id', 'selected' => $row['cost_code_id'] ?? null])
  $costCodeDivisions is the array CostCode::selectableFor($companyId)->toArray()
  returns (each division with its 'children' already loaded). Always optional —
  "— none —" is the default, first option, matching every existing row's
  cost_code_id = null. Optional $form attaches a form="..." attribute, for an
  input rendered outside its <form> tag (same pattern as boq.blade.php's
  inline-edit inputs).
--}}
@php
  $selectedId = isset($selected) && $selected !== null ? (string) $selected : '';
@endphp
<select name="{{ $name }}" @if(!empty($selectId)) id="{{ $selectId }}" @endif @if(!empty($form)) form="{{ $form }}" @endif class="cost-code-select">
  <option value="">{{ t('user.cost_codes.none') }}</option>
  @foreach ($costCodeDivisions as $division)
    <optgroup label="{{ $division['code'] }} {{ local($division, 'description') }}">
      <option value="{{ $division['id'] }}" {{ $selectedId === (string) $division['id'] ? 'selected' : '' }}>{{ $division['code'] }} — {{ local($division, 'description') }}</option>
      @foreach ($division['children'] ?? [] as $child)
        <option value="{{ $child['id'] }}" {{ $selectedId === (string) $child['id'] ? 'selected' : '' }}>&nbsp;&nbsp;{{ $child['code'] }} — {{ local($child, 'description') }}</option>
      @endforeach
    </optgroup>
  @endforeach
</select>
