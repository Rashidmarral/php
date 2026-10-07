@extends('layouts.admin')

@section('title', t('aside.dashboard'))

@section('content')
<div class="page-head">
  <h1>{{ t('aside.dashboard') }}</h1>
  <div style="display:flex;gap:8px;">
    <a href="/admin/reports" class="btn btn-light btn-sm">{{ t('admin.dashboard.full_reports') }}</a>
    <a href="/admin/usage" class="btn btn-light btn-sm">{{ t('admin.dashboard.usage_link') }}</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">{{ t('admin.dashboard.total_companies') }}</div>
    <div class="value">{{ $totalCompanies }}</div>
    <div class="delta">{{ t('admin.dashboard.active_trial_counts', ['active' => $statusMap['active'], 'trial' => $statusMap['trial']]) }}</div>
  </div>
  <div class="kpi">
    <div class="label">{{ t('admin.dashboard.revenue_this_month') }}</div>
    <div class="value">{{ number_format($revenueThisMonth, 0) }} <span style="font-size:14px;">SAR</span></div>
    @if($revenueDelta !== null)
      <div class="delta {{ $revenueDelta < 0 ? 'down' : '' }}">{{ $revenueDelta >= 0 ? '↑' : '↓' }} {{ t('admin.dashboard.vs_last_month', ['pct' => abs($revenueDelta)]) }}</div>
    @else
      <div class="delta">—</div>
    @endif
  </div>
  <div class="kpi">
    <div class="label">{{ t('admin.dashboard.est_mrr') }}</div>
    <div class="value">{{ number_format($mrr, 0) }} <span style="font-size:14px;">SAR</span></div>
    <div class="delta">{{ t('admin.dashboard.from_active_subscriptions') }}</div>
  </div>
  <div class="kpi">
    <div class="label">{{ t('admin.dashboard.zatca_phase2_live') }}</div>
    <div class="value">{{ $zatcaActive }}</div>
    <div class="delta">{{ t('admin.dashboard.of_total_companies', ['total' => $totalCompanies]) }}</div>
  </div>
</div>

<div class="grid grid-3" style="margin-bottom:26px;">
  <a href="/admin/payments" class="card feature-card" style="text-decoration:none;">
    <div class="icon">💳</div>
    <h3 style="font-size:15px;">{{ t('admin.dashboard.payments_pending_review', ['count' => $pendingPayments]) }}</h3>
    <p>{{ t('admin.dashboard.bank_transfer_receipts_desc') }}</p>
  </a>
  <a href="/admin/companies" class="card feature-card" style="text-decoration:none;">
    <div class="icon">⏳</div>
    <h3 style="font-size:15px;">{{ t('admin.dashboard.trials_ending_soon', ['count' => $expiringTrials]) }}</h3>
    <p>{{ t('admin.dashboard.trials_expiring_desc') }}</p>
  </a>
  <a href="/admin/consultations" class="card feature-card" style="text-decoration:none;">
    <div class="icon">🎓</div>
    <h3 style="font-size:15px;">{{ t('admin.dashboard.consultations_requested', ['count' => $pendingConsultations]) }}</h3>
    <p>{{ t('admin.dashboard.consultations_desc') }}</p>
  </a>
</div>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.plan_distribution') }}</h3>
    @foreach ($planCounts as $p)
      <div style="margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px;">
          <span>{{ $p->name }}</span><span style="color:var(--muted);">{{ $p->company_count }}</span>
        </div>
        <div style="background:var(--bg);border-radius:6px;height:8px;overflow:hidden;">
          <div style="background:linear-gradient(90deg,var(--brand),var(--brand-dark));height:100%;width:{{ $maxPlanCount > 0 ? round($p->company_count / $maxPlanCount * 100) : 0 }}%;"></div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.company_status') }}</h3>
    @php
      $dashboardStatusLabels = [
        'active' => t('admin.status.active'),
        'trial' => t('admin.status.trial'),
        'past_due' => t('admin.status.past_due'),
        'suspended' => t('admin.status.suspended'),
        'cancelled' => t('admin.status.cancelled'),
      ];
    @endphp
    @foreach ($statusMap as $status => $count)
      @continue($count === 0 && $status !== 'active' && $status !== 'trial')
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
        <span class="badge badge-{{ ['active'=>'green','trial'=>'blue','past_due'=>'yellow','suspended'=>'red','cancelled'=>'gray'][$status] ?? 'gray' }}">{{ $dashboardStatusLabels[$status] ?? ucfirst(str_replace('_',' ',$status)) }}</span>
        <strong>{{ $count }}</strong>
      </div>
    @endforeach
  </div>
</div>

<div class="grid grid-2" style="margin-top:24px;align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.recent_signups') }}</h3>
    @forelse ($recentCompanies as $c)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px;">
        <a href="/admin/companies/{{ $c->id }}">{{ $c->name }}</a>
        <span class="badge badge-{{ ['active'=>'green','trial'=>'blue'][$c->status] ?? 'gray' }}">{{ $dashboardStatusLabels[$c->status] ?? ucfirst($c->status) }}</span>
      </div>
    @empty
      <p class="help-text">{{ t('admin.dashboard.no_companies') }}</p>
    @endforelse
  </div>

  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.recent_payments_title') }}</h3>
    @php
      $dashboardPaymentStatusLabels = [
        'paid' => t('admin.payment.status_paid'),
        'pending' => t('admin.payment.status_pending'),
        'failed' => t('admin.payment.status_failed'),
        'refunded' => t('admin.payment.status_refunded'),
      ];
    @endphp
    @forelse ($recentPayments as $p)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px;">
        <span>{{ $p->company_name ?? '—' }}</span>
        <span>
          {{ number_format((float)$p->amount, 2) }} SAR
          <span class="badge badge-{{ ['paid'=>'green','pending'=>'yellow','failed'=>'red','refunded'=>'gray'][$p->status] ?? 'gray' }}">{{ $dashboardPaymentStatusLabels[$p->status] ?? ucfirst($p->status) }}</span>
        </span>
      </div>
    @empty
      <p class="help-text">{{ t('admin.dashboard.no_payments_yet') }}</p>
    @endforelse
  </div>
</div>
@endsection
