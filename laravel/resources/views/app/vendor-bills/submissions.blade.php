@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1>{{ t('user.vendor_bills.submissions_title') }}</h1>
</div>
<p class="help-text" style="margin-top:-8px;">{{ t('user.vendor_bills.submissions_hint') }}</p>

@if (empty($submissions))
  <div class="card empty-state">
    <div class="icon">📥</div>
    <h3>{{ t('user.vendor_bills.no_submissions_title') }}</h3>
    <p>{{ t('user.vendor_bills.no_submissions_hint') }}</p>
  </div>
@else
  <table class="data">
    <thead>
      <tr>
        <th>{{ t('common.supplier') }}</th>
        <th>{{ t('user.vendor_bills.project_col') }}</th>
        <th>{{ t('common.description') }}</th>
        <th>{{ t('common.category') }}</th>
        <th>{{ t('common.amount') }}</th>
        <th>{{ t('common.date') }}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    @foreach ($submissions as $s)
      <tr>
        <td>{{ $s['supplier_name'] }}</td>
        <td>{{ $s['project_name'] }}</td>
        <td>{{ $s['description'] }}@if (!empty($s['reference']))<br><span class="help-text">{{ $s['reference'] }}</span>@endif @if (!empty($s['file_path']))<br><a href="{{ $s['file_path'] }}" target="_blank" class="btn btn-sm btn-light">📎</a>@endif</td>
        <td><span class="badge badge-gray">{{ ucfirst($s['category']) }}</span></td>
        <td>{!! money((float) $s['amount']) !!}</td>
        <td class="help-text">{{ $s['bill_date'] }}</td>
        <td style="display:flex;gap:8px;">
          <form method="post" action="{{ url('/app/vendor-bill-submissions/' . $s['id'] . '/approve') }}">
            @csrf
            <button type="submit" class="btn btn-sm btn-primary">{{ t('user.vendor_bills.approve_btn') }}</button>
          </form>
          <form method="post" action="{{ url('/app/vendor-bill-submissions/' . $s['id'] . '/reject') }}" onsubmit="return rejectWithReason(this);">
            @csrf
            <input type="hidden" name="rejection_reason" value="">
            <button type="submit" class="btn btn-sm btn-danger">{{ t('user.vendor_bills.reject_btn') }}</button>
          </form>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
@endif

<script>
function rejectWithReason(form) {
  var reason = prompt({!! json_encode(t('user.vendor_bills.reject_reason_prompt')) !!}, '');
  if (reason === null) { return false; }
  form.querySelector('input[name="rejection_reason"]').value = reason;
  return true;
}
</script>
@endsection
