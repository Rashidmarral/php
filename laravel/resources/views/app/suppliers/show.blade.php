@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/suppliers">&larr; <?= t('user.suppliers.title') ?></a></p>
    <h1><?= e(local($supplier, 'name')) ?></h1>
    <p class="help-text" style="margin-top:4px;">
      <?php if ($supplier['category']): ?><?= e($supplier['category']) ?> · <?php endif; ?>
      <?= $supplier['trade_category'] ? e($supplier['trade_category']) : t('user.suppliers.no_trade_category') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
    <?php if ($supplier['is_approved_vendor']): ?>
      <span class="badge badge-green" style="font-size:13px;padding:6px 14px;"><?= t('user.suppliers.approved_vendor') ?></span>
    <?php else: ?>
      <span class="badge badge-gray" style="font-size:13px;padding:6px 14px;"><?= t('user.suppliers.not_approved_vendor') ?></span>
    <?php endif; ?>
    <?php if (auth()->user()->can('write')): ?>
      <form method="post" action="/app/suppliers/<?= $supplier['id'] ?>/toggle-approved">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-light"><?= $supplier['is_approved_vendor'] ? t('user.suppliers.unapprove_vendor_btn') : t('user.suppliers.approve_vendor_btn') ?></button>
      </form>
    <?php endif; ?>
    <a href="/app/suppliers/<?= $supplier['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
  </div>
</div>

<div class="kpi-grid" style="margin-bottom:20px;">
  <div class="kpi"><div class="label"><?= t('user.suppliers.classification_grade_col') ?></div><div class="value" style="font-size:18px;"><?= $supplier['classification_grade'] ? t('user.settings.classification_grade_option', ['n' => $supplier['classification_grade']]) : t('user.settings.not_classified') ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.suppliers.rating_col') ?></div><div class="value" style="font-size:18px;"><?= $averageRating !== null ? str_repeat('★', (int) round($averageRating)) . str_repeat('☆', 5 - (int) round($averageRating)) . ' (' . $averageRating . ')' : t('user.suppliers.no_ratings_yet') ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.suppliers.cr_number') ?></div><div class="value" style="font-size:18px;"><?= e($supplier['cr_number'] ?: '—') ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.suppliers.vat_number') ?></div><div class="value" style="font-size:18px;"><?= e($supplier['vat_number'] ?: '—') ?></div></div>
</div>

<?php if (!empty($supplier['approved_vendor_notes'])): ?>
  <div class="card" style="margin-bottom:20px;">
    <h3 style="font-size:14px;"><?= t('user.suppliers.approved_vendor_notes') ?></h3>
    <p style="margin:0;white-space:pre-line;"><?= e($supplier['approved_vendor_notes']) ?></p>
  </div>
<?php endif; ?>

<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.suppliers.documents_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.suppliers.documents_hint') ?></p>

  <?php if (auth()->user()->can('write')): ?>
  <form method="post" action="/app/suppliers/<?= $supplier['id'] ?>/documents" enctype="multipart/form-data" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.suppliers.document_type') ?></label><input type="text" name="doc_type" placeholder="e.g. cr_certificate, insurance, classification_certificate"></div>
      <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" required placeholder="e.g. Commercial Registration"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.document_number') ?></label><input type="text" name="document_number"></div>
      <div class="form-group"><label><?= t('user.business_setup.expiry_date') ?></label><input type="date" name="expiry_date"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.upload_optional') ?></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
      <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('user.suppliers.add_document_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($documents)): ?>
    <p class="help-text"><?= t('user.suppliers.no_documents_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('user.business_setup.document_col') ?></th><th><?= t('user.business_setup.number_col') ?></th><th><?= t('common.expiry') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($documents as $d):
        $daysLeft = $d['expiry_date'] ? (int) ceil((strtotime($d['expiry_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
        if ($daysLeft === null) { $badge = 'gray'; $label = t('user.business_setup.no_expiry_set'); }
        elseif ($daysLeft < 0) { $badge = 'red'; $label = t('user.business_setup.expired'); }
        elseif ($daysLeft <= 30) { $badge = 'red'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
        elseif ($daysLeft <= 60) { $badge = 'yellow'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
        else { $badge = 'green'; $label = t('user.business_setup.days_left', ['days' => $daysLeft]); }
      ?>
        <tr>
          <td>
            <?= e($d['name']) ?>
            <?php if ($d['doc_type']): ?><br><span class="help-text"><?= e($d['doc_type']) ?></span><?php endif; ?>
            <?php if ($d['file_path']): ?> · <a href="<?= e($d['file_path']) ?>" target="_blank"><?= t('user.business_setup.file_link') ?></a><?php endif; ?>
          </td>
          <td><?= e($d['document_number'] ?: '—') ?></td>
          <td><?= e($d['expiry_date'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $badge ?>"><?= e($label) ?></span></td>
          <td>
            <?php if (auth()->user()->can('write')): ?>
              <form method="post" action="/app/suppliers/<?= $supplier['id'] ?>/documents/<?= $d['id'] ?>/delete" onsubmit="return confirm('<?= t('user.business_setup.delete_document_confirm') ?>');">
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
</div>

<div class="card">
  <h3 style="font-size:14px;"><?= t('user.suppliers.ratings_title') ?></h3>
  <p class="help-text" style="margin-top:-8px;"><?= t('user.suppliers.ratings_hint') ?></p>

  <?php if (auth()->user()->can('write')): ?>
  <form method="post" action="/app/suppliers/<?= $supplier['id'] ?>/ratings" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('user.suppliers.score') ?></label>
        <select name="score" required>
          <?php foreach ([5, 4, 3, 2, 1] as $n): ?>
            <option value="<?= $n ?>"><?= str_repeat('★', $n) . str_repeat('☆', 5 - $n) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><?= t('user.suppliers.rating_project') ?></label>
        <select name="project_id">
          <option value=""><?= t('user.suppliers.rating_no_project') ?></option>
          <?php foreach ($projects as $p): ?>
            <option value="<?= $p['id'] ?>"><?= e(local($p, 'name')) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('user.suppliers.add_rating_btn') ?></button>
  </form>
  <?php endif; ?>

  <?php if (empty($ratings)): ?>
    <p class="help-text"><?= t('user.suppliers.no_ratings_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('user.suppliers.score') ?></th><th><?= t('user.suppliers.rating_project') ?></th><th><?= t('user.suppliers.rated_by') ?></th><th><?= t('common.date') ?></th><th><?= t('common.notes') ?></th></tr></thead>
      <tbody>
      <?php foreach ($ratings as $r): ?>
        <tr>
          <td><?= str_repeat('★', (int) $r['score']) . str_repeat('☆', 5 - (int) $r['score']) ?></td>
          <td><?= e($r['project_name'] ?: '—') ?></td>
          <td><?= e($r['rater_name']) ?></td>
          <td><?= e($r['created_at'] ? substr($r['created_at'], 0, 10) : '—') ?></td>
          <td><?= e($r['notes'] ?: '—') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
