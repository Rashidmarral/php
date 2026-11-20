@extends('layouts.admin')

@section('content')
<?php
$statusLabels = [
    'not_started' => t('admin.zatca.status_not_started'),
    'csr_generated' => t('admin.zatca.status_csr_generated'),
    'compliance_pending' => t('admin.zatca.status_compliance_pending'),
    'compliance_verified' => t('admin.zatca.status_compliance_verified'),
    'onboarded' => t('admin.zatca.status_onboarded'),
    'error' => t('admin.zatca.status_error'),
    'failed' => t('admin.zatca.status_error'),
];
$envLabels = ['developer' => t('admin.zatca.env_developer'), 'simulation' => t('admin.zatca.env_simulation'), 'production' => t('admin.zatca.env_production')];
?>
<div class="page-head">
  <div>
    <h1><?= t('admin.zatca_logs.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('admin.zatca_logs.index_hint') ?></p>
  </div>
  <a href="/admin/zatca/logs" class="btn btn-secondary"><?= t('admin.zatca_logs.view_all_logs') ?></a>
</div>

<div class="kpi-grid" style="margin-bottom:24px;">
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.connected') ?></div><div class="value"><?= number_format($stats['connected']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.pending_onboarding') ?></div><div class="value"><?= number_format($stats['pending_onboarding']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.failed_connections') ?></div><div class="value"><?= number_format($stats['failed_connections']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.total_submitted') ?></div><div class="value"><?= number_format($stats['total_submitted']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.accepted') ?></div><div class="value"><?= number_format($stats['accepted']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.rejected') ?></div><div class="value"><?= number_format($stats['rejected']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('admin.zatca_logs.failed_submissions') ?></div><div class="value"><?= number_format($stats['failed_submissions']) ?></div></div>
</div>

<form method="get" action="/admin/zatca" class="card" style="margin-bottom:20px;display:flex;gap:12px;align-items:end;flex-wrap:wrap;">
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.zatca_logs.company_or_vat') ?></label>
    <input type="text" name="q" value="<?= e(request('q')) ?>">
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.zatca.environment') ?></label>
    <select name="environment">
      <option value=""><?= t('admin.zatca_logs.all_environments') ?></option>
      <?php foreach (['developer', 'simulation', 'production'] as $env): ?>
        <option value="<?= $env ?>" <?= request('environment') === $env ? 'selected' : '' ?>><?= e($envLabels[$env]) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group" style="margin:0;">
    <label><?= t('admin.zatca_logs.integration_status') ?></label>
    <select name="status">
      <option value=""><?= t('common.all') ?></option>
      <option value="connected" <?= request('status') === 'connected' ? 'selected' : '' ?>><?= t('admin.zatca_logs.connected') ?></option>
      <option value="pending" <?= request('status') === 'pending' ? 'selected' : '' ?>><?= t('admin.zatca_logs.pending') ?></option>
      <option value="failed" <?= request('status') === 'failed' ? 'selected' : '' ?>><?= t('admin.zatca_logs.failed') ?></option>
      <option value="not_connected" <?= request('status') === 'not_connected' ? 'selected' : '' ?>><?= t('admin.zatca_logs.not_connected') ?></option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary"><?= t('common.filter') ?></button>
  <?php if (request()->anyFilled(['q', 'environment', 'status'])): ?>
    <a href="/admin/zatca" class="btn btn-light"><?= t('common.clear') ?></a>
  <?php endif; ?>
</form>

<?php if ($companies->isEmpty()): ?>
  <div class="card empty-state"><div class="icon">🧾</div><h3><?= t('common.no_data') ?></h3></div>
<?php else: ?>
  <table class="data">
    <thead>
      <tr>
        <th><?= t('common.company') ?></th>
        <th><?= t('admin.company.vat_number') ?></th>
        <th><?= t('admin.zatca.environment') ?></th>
        <th><?= t('admin.zatca.onboarding_status') ?></th>
        <th><?= t('admin.zatca_logs.last_submission') ?></th>
        <th><?= t('admin.zatca_logs.last_successful') ?></th>
        <th><?= t('admin.zatca_logs.failed_submissions') ?></th>
        <th><?= t('admin.zatca_logs.pending_sync') ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($companies as $company): ?>
        <?php
        $status = $company->zatca_status ?: 'not_started';
        $connected = $status === 'onboarded' && $company->zatca_production_csid;
        $badgeColor = $connected ? 'green' : ($status === 'failed' || $status === 'error' ? 'red' : ($status === 'not_started' ? 'gray' : 'yellow'));
        $lastSub = $lastSubmission[$company->id] ?? null;
        $lastOk = $lastSuccessful[$company->id] ?? null;
        $pending = $pendingCounts[$company->id] ?? null;
        ?>
        <tr>
          <td><a href="/admin/companies/<?= $company->id ?>"><?= e($company->name) ?></a></td>
          <td><?= e($company->vat_number ?: '—') ?></td>
          <td><?= e($envLabels[$company->zatca_environment] ?? ucfirst((string) $company->zatca_environment)) ?></td>
          <td><span class="badge badge-<?= $badgeColor ?>"><?= e($statusLabels[$status] ?? $status) ?></span></td>
          <td class="help-text"><?= $lastSub ? e(\Illuminate\Support\Carbon::parse($lastSub)->format('Y-m-d H:i')) : '—' ?></td>
          <td class="help-text"><?= $lastOk ? e(\Illuminate\Support\Carbon::parse($lastOk)->format('Y-m-d H:i')) : '—' ?></td>
          <td<?= ($failedCounts[$company->id] ?? 0) > 0 ? ' style="color:var(--danger);font-weight:600;"' : '' ?>><?= $failedCounts[$company->id] ?? 0 ?></td>
          <td>
            <?php if ($pending === null): ?>
              <span class="help-text">—</span>
            <?php else: ?>
              <a href="/admin/companies/<?= $company->id ?>/zatca/pending"><?= $pending ?></a>
            <?php endif; ?>
          </td>
          <td style="display:flex;gap:6px;white-space:nowrap;">
            <a href="/admin/companies/<?= $company->id ?>/zatca" class="btn btn-sm btn-outline"><?= t('admin.zatca_logs.onboarding') ?></a>
            <a href="/admin/zatca/logs?company_id=<?= $company->id ?>" class="btn btn-sm btn-outline"><?= t('admin.zatca_logs.view_logs') ?></a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  @include('admin.partials.pagination', ['paginator' => $companies])
<?php endif; ?>

<div class="card" style="margin-top:24px;">
  <h3 style="margin-bottom:12px;"><?= t('admin.zatca_logs.recent_errors') ?></h3>
  <?php if ($recentErrors->isEmpty()): ?>
    <p class="help-text"><?= t('admin.zatca_logs.no_recent_errors') ?></p>
  <?php else: ?>
    <?php foreach ($recentErrors as $error): ?>
      <div style="padding:10px 0;border-bottom:1px solid var(--border);">
        <div style="display:flex;justify-content:space-between;gap:12px;">
          <span><strong><?= e($error['company'] ?? t('admin.zatca_logs.unknown_company')) ?></strong> · <?= e($error['reference']) ?></span>
          <span class="help-text" style="white-space:nowrap;"><?= e($error['created_at']->format('Y-m-d H:i')) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;gap:12px;margin-top:4px;">
          <span style="color:var(--danger);"><?= e(\Illuminate\Support\Str::limit($error['error'] ?? t('admin.zatca_logs.submission_failed'), 140)) ?></span>
          <a href="/admin/zatca/logs/<?= $error['type'] ?>/<?= $error['log_id'] ?>" class="help-text" style="white-space:nowrap;"><?= t('common.view') ?></a>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
@endsection
