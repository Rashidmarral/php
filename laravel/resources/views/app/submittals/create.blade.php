@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>/submittals">&larr; <?= t('user.submittals.title') ?></a></p>
    <h1><?= t('user.submittals.new') ?></h1>
  </div>
</div>

<form method="post" action="/app/projects/<?= $project['id'] ?>/submittals" enctype="multipart/form-data" class="card" style="max-width:720px;">
  <?= csrf_field() ?>
  <div class="form-row">
    <div class="form-group"><label><?= t('common.title') ?></label><input type="text" name="title" required></div>
    <div class="form-group"><label><?= t('user.submittals.spec_section') ?></label><input type="text" name="spec_section" placeholder="e.g. 09 30 00"></div>
  </div>
  <div class="form-group"><label><?= t('common.description') ?></label><textarea name="description" rows="3"></textarea></div>
  <div class="form-row">
    <div class="form-group"><label><?= t('user.submittals.due_date') ?></label><input type="date" name="due_date"></div>
    <div class="form-group"><label><?= t('user.submittals.file') ?></label><input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.dwg,.zip"></div>
  </div>
  <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
  <button type="submit" class="btn btn-primary"><?= t('user.submittals.submit') ?></button>
</form>
@endsection
