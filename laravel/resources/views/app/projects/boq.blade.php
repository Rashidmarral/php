@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/projects/<?= $project['id'] ?>">&larr; <?= e(local($project, 'name')) ?></a></p>
    <h1><?= t('user.boq.title') ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;"><?= t('user.boq.hint') ?></p>

<?php if ($locked): ?>
  <div class="card" style="margin-bottom:20px;">
    <p style="margin:0;"><?= t('user.boq.locked_hint') ?></p>
  </div>
<?php endif; ?>

<div class="card">
  <?php if (empty($items)): ?>
    <p class="help-text"><?= t('user.boq.no_lines') ?></p>
  <?php else: ?>
    <div style="overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('user.boq.item_number') ?></th>
          <th><?= t('common.description_en') ?></th>
          <th><?= t('common.description_ar') ?></th>
          <th><?= t('user.boq.uom') ?></th>
          <th><?= t('user.boq.qty') ?></th>
          <th><?= t('user.boq.unit_price') ?></th>
          <th><?= t('user.boq.total') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php $lastSection = null; ?>
        <?php foreach ($items as $item): $fid = 'boq-' . $item['id']; ?>
          <?php if (!empty($item['section_title']) && $item['section_title'] !== $lastSection): $lastSection = $item['section_title']; ?>
            <tr><td colspan="8" style="background:var(--bg);font-weight:700;"><?= e(local($item, 'section_title')) ?></td></tr>
          <?php endif; ?>
          <?php if (!$locked): ?>
            <form id="<?= $fid ?>" method="post" action="/app/boq/<?= $item['id'] ?>"><?= csrf_field() ?></form>
          <?php endif; ?>
          <tr>
            <td style="min-width:80px;"><input form="<?= $fid ?>" type="text" name="item_number" value="<?= e($item['item_number'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?> style="width:70px;"></td>
            <td style="min-width:200px;"><input form="<?= $fid ?>" type="text" name="description" value="<?= e($item['description']) ?>" <?= $locked ? 'disabled' : '' ?> style="width:100%;"></td>
            <td style="min-width:160px;"><input form="<?= $fid ?>" type="text" name="description_ar" dir="rtl" value="<?= e($item['description_ar'] ?? '') ?>" <?= $locked ? 'disabled' : '' ?> style="width:100%;"></td>
            <td style="min-width:70px;"><input form="<?= $fid ?>" type="text" name="uom" value="<?= e($item['uom']) ?>" <?= $locked ? 'disabled' : '' ?> style="width:60px;"></td>
            <td style="min-width:90px;"><input form="<?= $fid ?>" type="number" step="0.01" name="qty" value="<?= e((string)$item['qty']) ?>" <?= $locked ? 'disabled' : '' ?> style="width:85px;"></td>
            <td style="min-width:100px;"><input form="<?= $fid ?>" type="number" step="0.01" name="unit_price" value="<?= e((string)$item['unit_price']) ?>" <?= $locked ? 'disabled' : '' ?> style="width:95px;"></td>
            <td style="white-space:nowrap;"><?= money((float)$item['total']) ?></td>
            <td style="white-space:nowrap;display:flex;gap:6px;">
              <?php if (!$locked): ?>
                <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light"><?= t('common.save') ?></button>
                <form method="post" action="/app/boq/<?= $item['id'] ?>/delete" onsubmit="return confirm('<?= t('user.boq.delete_confirm') ?>');" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="6" style="text-align:right;font-weight:700;"><?= t('user.boq.contract_value') ?></td>
          <td style="font-weight:700;"><?= money($contractValue) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:20px;max-width:900px;">
  <h3 style="font-size:14px;"><?= t('user.boq.add_line') ?></h3>
  <form method="post" action="/app/projects/<?= $project['id'] ?>/boq">
    <?= csrf_field() ?>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.boq.section_title') ?></label><input type="text" name="section_title" placeholder="e.g. 2.0 Concrete Works"></div>
      <div class="form-group"><label><?= t('user.boq.item_number') ?></label><input type="text" name="item_number" placeholder="e.g. 2.1"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.description_en') ?></label><input type="text" name="description" required></div>
      <div class="form-group"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl" placeholder="الوصف بالعربية"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.boq.uom') ?></label><input type="text" name="uom" placeholder="e.g. m3" required></div>
      <div class="form-group"><label><?= t('user.boq.qty') ?></label><input type="number" step="0.01" name="qty" value="0" required></div>
      <div class="form-group"><label><?= t('user.boq.unit_price') ?></label><input type="number" step="0.01" name="unit_price" value="0" required></div>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('user.boq.add_line') ?></button>
  </form>
</div>

<div class="card" style="margin-top:20px;max-width:900px;">
  <h3 style="font-size:14px;"><?= t('user.boq.import_title') ?></h3>
  <p class="help-text"><?= t('user.boq.import_hint') ?></p>
  <form method="post" action="/app/projects/<?= $project['id'] ?>/boq/import" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <input type="file" name="file" accept=".xlsx,.xls,.csv" required>
    <button type="submit" class="btn btn-sm btn-outline"><?= t('user.boq.import_button') ?></button>
    <a href="/app/projects/<?= $project['id'] ?>/boq/import-template" class="btn btn-sm btn-light"><?= t('user.boq.download_template') ?></a>
  </form>
</div>

@endsection
