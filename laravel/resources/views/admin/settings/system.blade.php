@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.settings.title') ?></h1>
</div>

@include('admin.settings.partials.tabs', ['active' => 'system'])

<div class="card" style="max-width:680px;margin-bottom:20px;">
  <h3 style="font-size:14px;"><?= t('admin.settings.system_diagnostics') ?></h3>
  <table class="data">
    <tbody>
    <tr><td><?= t('admin.settings.php_version') ?></td><td><?= e($phpVersion) ?></td></tr>
    <tr><td><?= t('admin.settings.laravel_version') ?></td><td><?= e($laravelVersion) ?></td></tr>
    <tr><td><?= t('admin.settings.environment') ?></td><td><?= e($environment) ?></td></tr>
    <tr><td><?= t('admin.settings.debug_mode') ?></td><td><span class="badge <?= $debugMode ? 'badge-yellow' : 'badge-green' ?>"><?= $debugMode ? t('admin.settings.on') : t('admin.settings.off') ?></span></td></tr>
    <tr><td><?= t('admin.settings.db_driver') ?></td><td><?= e($dbDriver) ?></td></tr>
    <tr><td><?= t('admin.settings.db_connection') ?></td><td><?= e($dbConnection) ?></td></tr>
    </tbody>
  </table>
  <p class="help-text" style="margin-top:10px;"><?= t('admin.settings.debug_mode_hint') ?></p>
</div>

<div class="card" style="max-width:680px;">
  <h3 style="font-size:14px;"><?= t('admin.settings.maintenance_actions') ?></h3>
  <p class="help-text"><?= t('admin.settings.maintenance_actions_hint') ?></p>
  <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
    <form method="post" action="/admin/settings/system/clear-cache">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-light"><?= t('admin.settings.clear_cache') ?></button>
    </form>
    <form method="post" action="/admin/settings/system/clear-views">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-light"><?= t('admin.settings.clear_views') ?></button>
    </form>
  </div>
</div>

@endsection
