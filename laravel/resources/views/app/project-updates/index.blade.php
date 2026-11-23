@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.project_updates.title') ?></h1>
  </div>
  <a href="/app/projects/<?= $project['id'] ?>/updates/create" class="btn btn-primary"><?= t('user.project_updates.new_title') ?></a>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.project_updates.hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.project_updates.new_title') ?></h3>

  <?php $aiTooltip = $aiConfigured ? '' : t('user.project_updates.ai_not_configured'); ?>
  <div style="margin-bottom:10px;" title="<?= e($aiTooltip) ?>">
    <button type="button" class="btn btn-outline" id="btn-draft-with-ai" <?= $aiConfigured ? '' : 'disabled' ?> title="<?= e($aiTooltip) ?>">✨ <?= t('user.project_updates.draft_with_ai') ?></button>
  </div>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/updates" id="project-update-form">
    <?= csrf_field() ?>
    <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" maxlength="150" placeholder="<?= t('user.project_updates.title_placeholder') ?>"></div>
    <div class="form-group"><label><?= t('user.project_updates.body') ?></label><textarea name="body" id="project-update-body" rows="5" required placeholder="<?= t('user.project_updates.body_placeholder') ?>"></textarea></div>
    <input type="hidden" name="submit_action" id="project-update-submit-action" value="draft">
    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn btn-outline" id="btn-save-draft"><?= t('user.project_updates.save_draft') ?></button>
      <button type="submit" class="btn btn-primary" id="btn-publish-now"><?= t('user.project_updates.publish_now') ?></button>
    </div>
  </form>
</div>

<?php if (empty($updates)): ?>
  <div class="empty-state card">
    <div class="icon">📣</div>
    <p><?= t('user.project_updates.none_yet') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.date') ?></th><th><?= t('common.title') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($updates as $update): ?>
      <tr>
        <td><?= e($update['created_at']) ?></td>
        <td><?= e($update['title'] ?: '—') ?></td>
        <td>
          <?php if ($update['published_at']): ?>
            <span class="badge badge-green"><?= t('user.project_updates.published_badge') ?></span>
          <?php else: ?>
            <span class="badge badge-gray"><?= t('user.project_updates.draft_badge') ?></span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:6px;justify-content:flex-end;">
          <?php if ($update['published_at']): ?>
            <form method="post" action="/app/project-updates/<?= $update['id'] ?>/unpublish">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.project_updates.unpublish') ?></button>
            </form>
          <?php else: ?>
            <form method="post" action="/app/project-updates/<?= $update['id'] ?>/publish">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.project_updates.publish') ?></button>
            </form>
          <?php endif; ?>
          <form method="post" action="/app/project-updates/<?= $update['id'] ?>/delete" onsubmit="return confirm('<?= t('user.project_updates.delete_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-light"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
      <tr>
        <td colspan="4" style="white-space:pre-line;color:var(--muted);"><?= e(\Illuminate\Support\Str::limit((string) $update['body'], 240)) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<script>
(function() {
  var csrfToken = <?= json_encode(csrf_token()) ?>;
  var projectId = <?= (int) $project['id'] ?>;

  var actionField = document.getElementById('project-update-submit-action');
  var saveDraftBtn = document.getElementById('btn-save-draft');
  var publishBtn = document.getElementById('btn-publish-now');
  if (saveDraftBtn) saveDraftBtn.addEventListener('click', function() { actionField.value = 'draft'; });
  if (publishBtn) publishBtn.addEventListener('click', function() { actionField.value = 'publish'; });

  var draftBtn = document.getElementById('btn-draft-with-ai');
  if (draftBtn && !draftBtn.disabled) {
    draftBtn.addEventListener('click', function() {
      draftBtn.disabled = true;
      var originalText = draftBtn.textContent;
      draftBtn.textContent = <?= json_encode(t('user.project_updates.drafting')) ?>;

      fetch('/app/projects/' + projectId + '/updates/draft-with-ai', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ _token: csrfToken })
      }).then(function(r) { return r.json(); }).then(function(data) {
        draftBtn.disabled = false;
        draftBtn.textContent = originalText;
        if (!data.ok) {
          alert(data.error || <?= json_encode(t('user.project_updates.draft_failed')) ?>);
          return;
        }
        document.getElementById('project-update-body').value = data.body;
      }).catch(function() {
        draftBtn.disabled = false;
        draftBtn.textContent = originalText;
        alert(<?= json_encode(t('user.project_updates.draft_failed')) ?>);
      });
    });
  }
})();
</script>
@endsection
