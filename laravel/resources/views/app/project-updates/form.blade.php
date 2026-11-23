@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/updates">&larr; <?= t('user.project_updates.title') ?></a></p>
    <h1><?= t('user.project_updates.new_title') ?></h1>
  </div>
</div>

<div class="card" style="max-width:720px;">
  <?php $aiTooltip = $aiConfigured ? '' : t('user.project_updates.ai_not_configured'); ?>
  <div style="margin-bottom:14px;" title="<?= e($aiTooltip) ?>">
    <button type="button" class="btn btn-outline" id="btn-draft-with-ai" <?= $aiConfigured ? '' : 'disabled' ?> title="<?= e($aiTooltip) ?>">✨ <?= t('user.project_updates.draft_with_ai') ?></button>
    <p class="help-text" style="margin-top:6px;margin-bottom:0;"><?= t('user.project_updates.draft_with_ai_hint') ?></p>
  </div>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/updates" id="project-update-form">
    <?= csrf_field() ?>
    <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" maxlength="150" placeholder="<?= t('user.project_updates.title_placeholder') ?>"></div>
    <div class="form-group"><label><?= t('user.project_updates.body') ?></label><textarea name="body" id="project-update-body" rows="8" required placeholder="<?= t('user.project_updates.body_placeholder') ?>"></textarea></div>
    <input type="hidden" name="submit_action" id="project-update-submit-action" value="draft">
    <div style="display:flex;gap:8px;">
      <button type="submit" class="btn btn-outline" id="btn-save-draft"><?= t('user.project_updates.save_draft') ?></button>
      <button type="submit" class="btn btn-primary" id="btn-publish-now"><?= t('user.project_updates.publish_now') ?></button>
    </div>
  </form>
</div>

<script>
(function() {
  var csrfToken = <?= json_encode(csrf_token()) ?>;
  var projectId = <?= (int) $project['id'] ?>;

  var form = document.getElementById('project-update-form');
  var actionField = document.getElementById('project-update-submit-action');
  document.getElementById('btn-save-draft').addEventListener('click', function() { actionField.value = 'draft'; });
  document.getElementById('btn-publish-now').addEventListener('click', function() { actionField.value = 'publish'; });

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
