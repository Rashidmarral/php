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

<div class="grid grid-3" style="align-items:start;">
  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.revenue_trend_title') }}</h3>
    <div class="chart-box"><canvas id="chart-revenue-trend"></canvas></div>
  </div>

  <div class="card">
    <h3 style="margin-bottom:14px;">{{ t('admin.dashboard.plan_distribution') }}</h3>
    @if ($planCounts->sum('company_count') > 0)
      <div class="chart-box"><canvas id="chart-plan-distribution"></canvas></div>
    @else
      <p class="help-text">{{ t('admin.dashboard.no_companies') }}</p>
    @endif
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
    @if ($totalCompanies > 0)
      <div class="chart-box"><canvas id="chart-company-status"></canvas></div>
    @else
      <p class="help-text">{{ t('admin.dashboard.no_companies') }}</p>
    @endif
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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
(function () {
  if (typeof Chart === 'undefined') return;
  var css = getComputedStyle(document.documentElement);
  var brand = css.getPropertyValue('--brand').trim() || '#1f8a5f';
  var brandDark = css.getPropertyValue('--brand-dark').trim() || '#0f5c3c';
  var accent = css.getPropertyValue('--accent').trim() || '#e0a526';
  var muted = css.getPropertyValue('--muted').trim() || '#64708a';
  var border = css.getPropertyValue('--border').trim() || '#e2e5ee';
  Chart.defaults.font.family = "'Cairo', sans-serif";
  Chart.defaults.color = muted;

  var revenueTrend = @json($revenueTrend);
  var revenueCanvas = document.getElementById('chart-revenue-trend');
  if (revenueCanvas) {
    new Chart(revenueCanvas, {
      type: 'line',
      data: {
        labels: revenueTrend.map(function (r) { return r.label; }),
        datasets: [{
          data: revenueTrend.map(function (r) { return r.total; }),
          borderColor: brand,
          backgroundColor: brand + '22',
          fill: true,
          tension: 0.35,
          pointRadius: 3,
          pointBackgroundColor: brand,
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, grid: { color: border }, ticks: { callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; } } },
          x: { grid: { display: false } },
        },
      },
    });
  }

  var planCanvas = document.getElementById('chart-plan-distribution');
  if (planCanvas) {
    var planLabels = @json($planCounts->pluck('name'));
    var planData = @json($planCounts->pluck('company_count'));
    new Chart(planCanvas, {
      type: 'bar',
      data: {
        labels: planLabels,
        datasets: [{ data: planData, backgroundColor: brand, borderRadius: 6, maxBarThickness: 28 }],
      },
      options: {
        indexAxis: 'y',
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: border } },
          y: { grid: { display: false } },
        },
      },
    });
  }

  var statusCanvas = document.getElementById('chart-company-status');
  if (statusCanvas) {
    var statusMap = @json($statusMap);
    var statusLabels = @json($dashboardStatusLabels ?? []);
    var statusColors = { active: '#1f9d55', trial: '#2563eb', past_due: '#e0a526', suspended: '#dc2626', cancelled: muted };
    var entries = Object.keys(statusMap).filter(function (k) { return statusMap[k] > 0; });
    new Chart(statusCanvas, {
      type: 'doughnut',
      data: {
        labels: entries.map(function (k) { return statusLabels[k] || k; }),
        datasets: [{
          data: entries.map(function (k) { return statusMap[k]; }),
          backgroundColor: entries.map(function (k) { return statusColors[k] || accent; }),
          borderWidth: 0,
        }],
      },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 10, font: { size: 11 } } } },
      },
    });
  }
})();
</script>
@endpush
