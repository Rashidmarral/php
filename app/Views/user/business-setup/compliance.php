<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1><?= t('user.business_setup.title') ?></h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types"><?= t('user.business_setup.tab_building_types') ?></a>
  <a href="/app/business-setup/contact-types"><?= t('user.business_setup.tab_contact_types') ?></a>
  <a href="/app/business-setup/client-types"><?= t('user.business_setup.tab_client_types') ?></a>
  <a href="/app/business-setup/units-of-measure"><?= t('user.business_setup.tab_units') ?></a>
  <a href="/app/business-setup/tax-rates"><?= t('user.business_setup.tab_tax_rates') ?></a>
  <a href="/app/business-setup/compliance" class="active"><?= t('user.business_setup.tab_compliance') ?></a>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  <?= t('user.business_setup.compliance_hint') ?>
</p>

<?php if (Auth::can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;"><?= t('user.business_setup.add_document') ?></h3>
  <form method="post" action="/app/business-setup/compliance" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group">
        <label><?= t('common.type') ?></label>
        <select name="doc_type">
          <?php foreach ($types as $key => $label): ?><option value="<?= $key ?>"><?= View::e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label><?= t('common.name_en') ?></label><input type="text" name="name" placeholder="e.g. Commercial Registration" required></div>
      <div class="form-group"><label><?= t('common.name_ar') ?></label><input type="text" name="name_ar" dir="rtl" placeholder="السجل التجاري"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.document_number') ?></label><input type="text" name="document_number"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.business_setup.expiry_date') ?></label><input type="date" name="expiry_date"></div>
      <div class="form-group"><label><?= t('user.business_setup.upload_optional') ?></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
    </div>
    <div class="form-group"><label><?= t('common.notes') ?></label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary"><?= t('user.business_setup.add_document_btn') ?></button>
  </form>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card">
    <div class="icon">📜</div>
    <p><?= t('user.business_setup.no_documents_hint') ?></p>
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
          <?= View::e(View::local($r, 'name')) ?>
          <br><span class="help-text"><?= View::e($types[$r['doc_type']] ?? ucfirst($r['doc_type'])) ?></span>
          <?php if ($r['file_path']): ?> · <a href="<?= View::e($r['file_path']) ?>" target="_blank"><?= t('user.business_setup.file_link') ?></a><?php endif; ?>
        </td>
        <td><?= View::e($r['document_number'] ?: '—') ?></td>
        <td><?= View::e($r['expiry_date'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $badge ?>"><?= View::e($label) ?></span></td>
        <td>
          <?php if (Auth::can('manage_business_setup')): ?>
            <form method="post" action="/app/business-setup/compliance/<?= $r['id'] ?>/delete" onsubmit="return confirm('<?= t('user.business_setup.delete_document_confirm') ?>');">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
