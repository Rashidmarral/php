@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/rfis">&larr; <?= t('user.rfi.title') ?></a></p>
    <h1><?= t('user.rfi.new') ?></h1>
  </div>
</div>

<form method="post" action="/app/projects/<?= $project['id'] ?>/rfis" enctype="multipart/form-data" class="card" style="max-width:720px;">
  <?= csrf_field() ?>
  <div class="form-group"><label><?= t('user.rfi.subject') ?></label><input type="text" name="subject" required></div>
  <div class="form-group"><label><?= t('user.rfi.question') ?></label><textarea name="question" rows="6" required placeholder="<?= t('user.rfi.question_hint') ?>"></textarea></div>
  <div class="form-row">
    <div class="form-group">
      <label><?= t('user.rfi.assigned_to') ?></label>
      <select name="assigned_to">
        <option value=""><?= t('user.punch_list.unassigned') ?></option>
        <?php foreach ($teamMembers as $member): ?><option value="<?= $member->id ?>"><?= e($member->name) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label><?= t('user.rfi.due_date') ?></label><input type="date" name="due_date"></div>
  </div>
  <?php if ($documents->isNotEmpty()): ?>
  <div class="form-group">
    <label><?= t('user.rfi.reference_document') ?></label>
    <select name="document_id">
      <option value=""><?= t('common.none') ?></option>
      <?php foreach ($documents as $doc): ?><option value="<?= $doc->id ?>"><?= e(local($doc, 'name')) ?></option><?php endforeach; ?>
    </select>
  </div>
  <?php endif; ?>
  <div class="form-group"><label><?= t('user.support.attachment') ?></label><input type="file" name="attachment" accept="image/png,image/jpeg,image/webp,application/pdf"></div>
  <button type="submit" class="btn btn-primary"><?= t('user.rfi.submit') ?></button>
</form>
@endsection
