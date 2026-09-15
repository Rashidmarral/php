@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/team">&larr; <?= t('user.team.title') ?></a></p>
    <h1><?= e($member['name']) ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  <?= t('user.team_docs.hint') ?>
</p>

<?php if (auth()->user()->can('manage_team')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.team_docs.add_document') ?></h3>
  <form method="post" action="/app/team/<?= $member['id'] ?>/documents" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('common.type') ?></label>
        <select name="doc_type">
          <?php foreach ($types as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" placeholder="e.g. Iqama" required></div>
      <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" placeholder="الإقامة"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.document_number') ?></label><input type="text" name="document_number"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.expiry_date') ?></label><input type="date" name="expiry_date"></div>
      <div class="form-group"><label><?= t('user.business_setup.upload_optional') ?></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
    </div>
    <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('user.team_docs.add_document_btn') ?></button>
  </form>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card">
    <div class="icon">🪪</div>
    <p><?= t('user.team_docs.no_documents_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('user.business_setup.document_col') ?></th><th><?= t('user.business_setup.number_col') ?></th><th><?= t('common.expiry') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $daysLeft = $r['expiry_date'] ? (int) ceil((strtotime($r['expiry_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
      if ($daysLeft === null) { $badge = 'gray'; $label = t('user.business_setup.no_expiry_set'); }
      elseif ($daysLeft < 0) { $badge = 'red'; $label = t('user.business_setup.expired'); }
      elseif ($daysLeft <= 30) { $badge = 'red'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
      elseif ($daysLeft <= 60) { $badge = 'yellow'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
      else { $badge = 'green'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
    ?>
      <tr>
        <td>
          <?= e(local($r, 'name')) ?>
          <br><span class="help-text"><?= e($types[$r['doc_type']] ?? ucfirst($r['doc_type'])) ?></span>
          <?php if ($r['file_path']): ?> · <a href="<?= e($r['file_path']) ?>" target="_blank"><?= t('user.business_setup.file_link') ?></a><?php endif; ?>
        </td>
        <td><?= e($r['document_number'] ?: '—') ?></td>
        <td><?= e($r['expiry_date'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $badge ?>"><?= e($label) ?></span></td>
        <td>
          <?php if (auth()->user()->can('manage_team')): ?>
            <details style="display:inline-block;">
              <summary class="btn btn-sm btn-light" style="cursor:pointer;display:inline-block;"><?= t('common.edit') ?></summary>
              <div class="card" style="margin-top:8px;min-width:320px;">
                <form method="post" action="/app/team/<?= $member['id'] ?>/documents/<?= $r['id'] ?>" enctype="multipart/form-data">
                  <?= csrf_field() ?>
                  <div class="form-row">
                    <div class="form-group">
                      <label><?= t('common.type') ?></label>
                      <select name="doc_type">
                        <?php foreach ($types as $key => $label): ?><option value="<?= $key ?>" <?= $r['doc_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                      </select>
                    </div>
                    <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" value="<?= e($r['name']) ?>" required></div>
                    <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" value="<?= e($r['name_ar'] ?? '') ?>"></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group"><label><?= t('user.business_setup.document_number') ?></label><input type="text" name="document_number" value="<?= e($r['document_number'] ?? '') ?>"></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group"><label><?= t('user.business_setup.expiry_date') ?></label><input type="date" name="expiry_date" value="<?= e($r['expiry_date'] ?? '') ?>"></div>
                    <div class="form-group"><label><?= t('user.business_setup.upload_optional') ?></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
                  </div>
                  <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes" value="<?= e($r['notes'] ?? '') ?>"></div>
                  <button type="submit" class="btn btn-primary btn-sm"><?= t('common.save') ?></button>
                </form>
              </div>
            </details>
            <form method="post" action="/app/team/<?= $member['id'] ?>/documents/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= t('user.team_docs.delete_document_confirm') ?>');" style="display:inline-block;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
