@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.backups.title') ?></h1>
</div>
<p class="help-text" style="max-width:760px;margin-top:-8px;margin-bottom:20px;"><?= t('admin.backups.intro') ?></p>

<div class="card" style="max-width:760px;margin-bottom:20px;">
  <div style="display:flex;justify-content:space-between;align-items:center;">
    <h3 style="margin:0;"><?= t('admin.backups.create_backup') ?></h3>
    <span class="badge badge-blue"><?= e(strtoupper($driver)) ?></span>
  </div>
  <p class="help-text">
    <?= $driver === 'mysql' ? t('admin.backups.mysql_hint') : ($driver === 'sqlite' ? t('admin.backups.sqlite_hint') : t('admin.backups.unsupported_driver')) ?>
  </p>
  <form method="post" action="/admin/backups/create">
    <?= csrf_field() ?>
    <button type="submit" class="btn btn-primary" <?= in_array($driver, ['mysql', 'sqlite'], true) ? '' : 'disabled' ?>><?= t('admin.backups.create_backup') ?></button>
  </form>
</div>

<div class="card" style="max-width:760px;">
  <h3><?= t('admin.backups.existing_backups') ?> (<?= count($backups) ?>)</h3>
  @if(empty($backups))
    <p class="help-text"><?= t('admin.backups.none_yet') ?></p>
  @else
    <table class="data">
      <thead><tr><th><?= t('admin.backups.filename') ?></th><th><?= t('admin.backups.size') ?></th><th><?= t('admin.backups.created_at') ?></th><th></th></tr></thead>
      <tbody>
      @foreach ($backups as $backup)
        <tr>
          <td>{{ $backup['name'] }}</td>
          <td class="help-text">{{ formatBytes($backup['size']) }}</td>
          <td class="help-text">{{ date('Y-m-d H:i', $backup['modified']) }}</td>
          <td style="display:flex;gap:6px;">
            <a href="/admin/backups/{{ $backup['name'] }}/download" class="btn btn-sm btn-light"><?= t('admin.backups.download') ?></a>
            <form method="post" action="/admin/backups/{{ $backup['name'] }}/delete" onsubmit="return confirm('<?= t('admin.backups.confirm_delete') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
