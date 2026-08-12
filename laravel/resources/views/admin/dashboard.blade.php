@extends('layouts.admin')

@section('title', t('aside.dashboard'))

@section('content')
<div class="page-head">
  <h1>{{ t('aside.dashboard') }}</h1>
  <div style="display:flex;gap:8px;">
    <a href="/admin/reports" class="btn btn-light btn-sm">Full reports →</a>
    <a href="/admin/usage" class="btn btn-light btn-sm">Usage →</a>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi">
    <div class="label">{{ t('admin.dashboard.total_companies') }}</div>
    <div class="value">{{ $totalCompanies }}</div>
    <div class="delta">{{ $statusMap['active'] }} active · {{ $statusMap['trial'] }} trial</div>
  </div>
  <div class="kpi">
    <div class="label">Revenue this month</div>
    <div class="value">{{ number_format($revenueThisMonth, 0) }} <span style="font-size:14px;">SAR</span></div>
    @if($revenueDelta !== null)
      <div class="delta {{ $revenueDelta < 0 ? 'down' : '' }}">{{ $revenueDelta >= 0 ? '↑' : '↓' }} {{ abs($revenueDelta) }}% vs last month</div>
    @else
      <div class="delta">—</div>
    @endif
  </div>
  <div class="kpi">
    <div class="label">Est. MRR</div>
    <div class="value">{{ number_format($mrr, 0) }} <span style="font-size:14px;">SAR</span></div>
    <div class="delta">From active subscriptions</div>
  </div>
  <div class="kpi">
    <div class="label">ZATCA Phase 2 live</div>
    <div class="value">{{ $zatcaActive }}</div>
    <div class="delta">of {{ $totalCompanies }} companies</div>
  </div>
</div>

<div class="grid grid-3" style="margin-bottom:26px;">
  <a href="/admin/payments" class="card feature-card" style="text-decoration:none;">
    <div class="icon">💳</div>
    <h3 style="font-size:15px;">{{ $pendingPayments }} payment{{ $pendingPayments === 1 ? '' : 's' }} pending review</h3>
    <p>Bank transfer receipts and payments waiting on approval.</p>
  </a>
  <a href="/admin/companies" class="card feature-card" style="text-decoration:none;">
    <div class="icon">⏳</div>
    <h3 style="font-size:15px;">{{ $expiringTrials }} trial{{ $expiringTrials === 1 ? '' : 's' }} ending soon</h3>
    <p>Trials expiring within 3 days — candidates for a follow-up.</p>
  </a>
  <a href="/admin/consultations" class="card feature-card" style="text-decoration:none;">
    <div class="icon">🎓</div>
    <h3 style="font-size:15px;">{{ $pendingConsultations }} consultation{{ $pendingConsultations === 1 ? '' : 's' }} requested</h3>
    <p>Expert consultation requests awaiting scheduling.</p>
  </a>
</div>

<div class="grid grid-2" style="align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;">Plan distribution</h3>
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
    <h3 style="margin-bottom:14px;">Company status</h3>
    @foreach ($statusMap as $status => $count)
      @continue($count === 0 && $status !== 'active' && $status !== 'trial')
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);">
        <span class="badge badge-{{ ['active'=>'green','trial'=>'blue','past_due'=>'yellow','suspended'=>'red','cancelled'=>'gray'][$status] ?? 'gray' }}">{{ ucfirst(str_replace('_',' ',$status)) }}</span>
        <strong>{{ $count }}</strong>
      </div>
    @endforeach
  </div>
</div>

<div class="grid grid-2" style="margin-top:24px;align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;">Recent signups</h3>
    @forelse ($recentCompanies as $c)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px;">
        <a href="/admin/companies/{{ $c->id }}">{{ $c->name }}</a>
        <span class="badge badge-{{ ['active'=>'green','trial'=>'blue'][$c->status] ?? 'gray' }}">{{ ucfirst($c->status) }}</span>
      </div>
    @empty
      <p class="help-text">No companies yet.</p>
    @endforelse
  </div>

  <div class="card">
    <h3 style="margin-bottom:14px;">Recent payments</h3>
    @forelse ($recentPayments as $p)
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border);font-size:13.5px;">
        <span>{{ $p->company_name ?? '—' }}</span>
        <span>
          {{ number_format((float)$p->amount, 2) }} SAR
          <span class="badge badge-{{ ['paid'=>'green','pending'=>'yellow','failed'=>'red','refunded'=>'gray'][$p->status] ?? 'gray' }}">{{ ucfirst($p->status) }}</span>
        </span>
      </div>
    @empty
      <p class="help-text">No payments yet.</p>
    @endforelse
  </div>
</div>
@endsection
