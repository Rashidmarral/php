<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Business Setup</h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types">Building Types</a>
  <a href="/app/business-setup/contact-types">Contact Types</a>
  <a href="/app/business-setup/client-types">Client Types</a>
  <a href="/app/business-setup/units-of-measure">Units of Measure</a>
  <a href="/app/business-setup/tax-rates">Tax Rates</a>
  <a href="/app/business-setup/compliance" class="active">Compliance Documents</a>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  Track the documents that keep you eligible to bid on Etimad government tenders — CR, VAT, Zakat,
  GOSI, Chamber of Commerce, and Nitaqat — with a reminder email before each one expires.
</p>

<?php if (Auth::can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3 style="font-size:14px;">Add a document</h3>
  <form method="post" action="/app/business-setup/compliance" enctype="multipart/form-data">
    <?= Csrf::field() ?>
    <div class="form-row">
      <div class="form-group">
        <label>Type</label>
        <select name="doc_type">
          <?php foreach ($types as $key => $label): ?><option value="<?= $key ?>"><?= View::e($label) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Name (English)</label><input type="text" name="name" placeholder="e.g. Commercial Registration" required></div>
      <div class="form-group"><label>Name (Arabic)</label><input type="text" name="name_ar" dir="rtl" placeholder="السجل التجاري"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Document number</label><input type="text" name="document_number"></div>
    </div>
    <div class="form-row">
      <div class="form-group"><label>Expiry date</label><input type="date" name="expiry_date"></div>
      <div class="form-group"><label>Upload (PDF/JPG/PNG, optional)</label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
    </div>
    <div class="form-group"><label>Notes</label><input type="text" name="notes"></div>
    <button type="submit" class="btn btn-primary">Add document</button>
  </form>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card">
    <div class="icon">📜</div>
    <p>No compliance documents yet. Add your CR, VAT, Zakat, GOSI, and Chamber of Commerce records to get renewal reminders.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Document</th><th>Number</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
      $daysLeft = $r['expiry_date'] ? (int) ceil((strtotime($r['expiry_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
      if ($daysLeft === null) { $badge = 'gray'; $label = 'No expiry set'; }
      elseif ($daysLeft < 0) { $badge = 'red'; $label = 'Expired'; }
      elseif ($daysLeft <= 30) { $badge = 'red'; $label = "{$daysLeft}d left"; }
      elseif ($daysLeft <= 60) { $badge = 'yellow'; $label = "{$daysLeft}d left"; }
      else { $badge = 'green'; $label = "{$daysLeft}d left"; }
    ?>
      <tr>
        <td>
          <?= View::e(View::local($r, 'name')) ?>
          <br><span class="help-text"><?= View::e($types[$r['doc_type']] ?? ucfirst($r['doc_type'])) ?></span>
          <?php if ($r['file_path']): ?> · <a href="<?= View::e($r['file_path']) ?>" target="_blank">File</a><?php endif; ?>
        </td>
        <td><?= View::e($r['document_number'] ?: '—') ?></td>
        <td><?= View::e($r['expiry_date'] ?: '—') ?></td>
        <td><span class="badge badge-<?= $badge ?>"><?= View::e($label) ?></span></td>
        <td>
          <?php if (Auth::can('manage_business_setup')): ?>
            <form method="post" action="/app/business-setup/compliance/<?= $r['id'] ?>/delete" onsubmit="return confirm('Delete this document?');">
              <?= Csrf::field() ?>
              <button type="submit" class="btn btn-sm btn-danger">Delete</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
