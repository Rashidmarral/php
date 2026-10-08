@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.site_log.title') ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.site_log.hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.site_log.add_entry') ?></h3>
  <form method="post" action="/app/projects/<?= $project['id'] ?>/site-log" id="site-log-form">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.date') ?></label><input type="date" name="log_date" value="<?= date('Y-m-d') ?>"></div>
      <div class="form-group"><label><?= t('user.site_log.weather') ?></label><input type="text" name="weather" placeholder="e.g. Sunny, 38°C"></div>
    </div>
    <div class="form-group" style="max-width:220px;"><label><?= t('user.site_log.workers_on_site') ?></label><input type="number" min="0" name="workers_on_site" placeholder="e.g. 24"></div>
    <div class="form-group"><label><?= t('user.site_log.notes') ?></label><textarea name="notes" rows="4" placeholder="<?= t('user.site_log.notes_placeholder') ?>"></textarea></div>
    <button type="submit" class="btn btn-primary"><?= t('user.site_log.add_entry_btn') ?></button>
    <p class="help-text" id="site-log-offline-note" hidden style="margin-top:10px;"><?= t('user.site_log.saved_offline') ?></p>
  </form>
</div>

<?php if (empty($logs)): ?>
  <div class="empty-state card">
    <div class="icon">📝</div>
    <p><?= t('user.site_log.no_entries_hint') ?></p>
  </div>
<?php else: ?>
  <div class="card-list">
    <?php foreach ($logs as $log): ?>
      <div class="card" style="margin-bottom:14px;">
        <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:8px;">
          <strong><?= e($log['log_date']) ?></strong>
          <span class="help-text">
            <?php if ($log['weather']): ?>☀️ <?= e($log['weather']) ?><?php endif; ?>
            <?php if ($log['workers_on_site'] !== null): ?> · 👷 <?= e((string)$log['workers_on_site']) ?> <?= t('user.site_log.workers_label') ?><?php endif; ?>
          </span>
        </div>
        <?php if ($log['notes']): ?><p style="margin:8px 0 0;white-space:pre-line;"><?= e($log['notes']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
(function() {
  // Best-effort offline queue: if the quick-entry form fails to submit because there's no
  // network, stash it in localStorage and retry automatically once connectivity returns —
  // the single most likely "typed a note with no signal" scenario for a site supervisor.
  // This is NOT a general sync framework: it only ever holds this one form's data, keyed on
  // this project, and only for as long as this page/tab is open to retry it.
  var STORAGE_KEY = 'buildxact-site-log-queue-<?= (int) $project['id'] ?>';
  var form = document.getElementById('site-log-form');
  var offlineNote = document.getElementById('site-log-offline-note');
  if (!form) { return; }

  function readQueue() {
    try { return JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
  }
  function writeQueue(items) {
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(items)); } catch (e) { /* ignore */ }
  }
  function csrfToken() {
    var input = form.querySelector('input[name=_token]');
    return input ? input.value : '';
  }
  function flushQueue() {
    var items = readQueue();
    if (!items.length || !navigator.onLine) { return; }
    var remaining = items.slice();
    (function sendNext() {
      if (!remaining.length) { writeQueue([]); return; }
      var entry = remaining[0];
      var body = new URLSearchParams(entry);
      fetch(form.action, { method: 'POST', body: body, headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
        .then(function(res) {
          if (!res.ok && res.status !== 302) { throw new Error('queued entry rejected'); }
          remaining.shift(); writeQueue(remaining); sendNext();
        })
        .catch(function() { writeQueue(remaining); });
    })();
  }

  form.addEventListener('submit', function(e) {
    if (navigator.onLine) { return; }
    e.preventDefault();
    var data = {};
    new FormData(form).forEach(function(value, key) { data[key] = value; });
    data._token = csrfToken();
    var items = readQueue();
    items.push(data);
    writeQueue(items);
    offlineNote.hidden = false;
    form.reset();
  });

  window.addEventListener('online', flushQueue);
  flushQueue();
})();
</script>
@endsection
