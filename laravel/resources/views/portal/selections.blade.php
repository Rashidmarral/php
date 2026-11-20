@extends('layouts.portal')

{{--
  Client-facing Selections page: this project's allowance items grouped by category,
  each with its options rendered as pickable cards (image if present, name, price) and
  a "Choose this" button that posts to PortalController::chooseOption(). Once a choice
  has been made, that option is marked instead of offered again, and the allowance vs.
  chosen-price variance is shown the same over/under coloring convention
  reports/profit.blade.php uses on the company side.
--}}
@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="{{ url('/portal/projects/' . $project->id) }}">&larr; {{ $project->name }}</a></p>
    <h1>{{ t('portal.selections.title') }}</h1>
  </div>
</div>

@if (empty($grouped))
  <div class="card"><p class="help-text">{{ t('portal.selections.none_yet') }}</p></div>
@else
  @foreach ($grouped as $category => $items)
    <div class="card" style="margin-bottom:20px;">
      <h3>{{ $category }}</h3>
      @foreach ($items as $selection)
        @php
          $chosen = $selection['chosen_option'];
          $variance = $chosen ? round((float) $chosen['price'] - (float) $selection['allowance_amount'], 2) : null;
        @endphp
        <div style="border-top:1px solid var(--border);padding-top:14px;margin-top:14px;">
          <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;">
            <div>
              <strong>{{ local($selection, 'name') }}</strong>
              <div><span class="badge badge-blue">{{ $statuses[$selection['status']] ?? $selection['status'] }}</span></div>
              @if (!empty($selection['description']))
                <p class="help-text" style="margin:6px 0 0;max-width:420px;">{{ local($selection, 'description') }}</p>
              @endif
            </div>
            <div style="text-align:end;">
              <div class="help-text">{{ t('portal.selections.allowance') }}</div>
              <div style="font-weight:700;">{!! money((float) $selection['allowance_amount']) !!}</div>
            </div>
          </div>

          <div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:14px;">
            @foreach ($selection['options'] as $option)
              <div style="border:1px solid var(--border);border-radius:8px;padding:12px;width:200px;{{ $option['is_chosen'] ? 'border-color:var(--success);box-shadow:0 0 0 2px rgba(16,163,74,.15);' : '' }}">
                @if (!empty($option['image_path']))
                  <img src="{{ $option['image_path'] }}" style="width:100%;height:110px;object-fit:cover;border-radius:6px;margin-bottom:8px;">
                @endif
                <div style="font-weight:600;">{{ local($option, 'name') }}</div>
                <div class="help-text" style="margin:4px 0 10px;">{!! money((float) $option['price']) !!}</div>
                @if ($option['is_chosen'])
                  <span class="badge badge-green">{{ t('portal.selections.your_pick') }}</span>
                @else
                  <form method="post" action="{{ url('/portal/selections/' . $selection['id'] . '/options/' . $option['id'] . '/choose') }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-primary" style="width:100%;">{{ t('portal.selections.choose_this') }}</button>
                  </form>
                @endif
              </div>
            @endforeach
          </div>

          @if ($chosen)
            <p style="margin-top:12px;font-weight:600;color:{{ $variance > 0 ? 'var(--danger)' : 'var(--success)' }};">
              {{ $variance > 0 ? t('portal.selections.over_allowance') : t('portal.selections.under_allowance') }}
              ({{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 2) }} SAR)
            </p>
          @endif
        </div>
      @endforeach
    </div>
  @endforeach
@endif
@endsection
