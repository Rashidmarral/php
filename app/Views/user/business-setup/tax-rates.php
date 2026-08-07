<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Business Setup</h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types">Building Types</a>
  <a href="/app/business-setup/contact-types">Contact Types</a>
  <a href="/app/business-setup/client-types">Client Types</a>
  <a href="/app/business-setup/units-of-measure">Units of Measure</a>
  <a href="/app/business-setup/tax-rates" class="active">Tax Rates</a>
  <a href="/app/business-setup/compliance">Compliance Documents</a>
</div>

<?php if (Auth::can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;">Tax Rates</h3>
  <p class="help-text" style="margin-top:-6px;">The default rate is applied automatically to new invoices — this doesn't change ZATCA's required 15% VAT reporting, it's for internal reference and any additional/local rates.</p>
  <form method="post" action="/app/business-setup/tax-rates" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 120px 120px auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Name (English)</label><input type="text" name="name" placeholder="e.g. Standard VAT" required></div>
    <div class="form-group" style="margin:0;"><label>Name (Arabic)</label><input type="text" name="name_ar" dir="rtl" placeholder="ضريبة القيمة المضافة"></div>
    <div class="form-group" style="margin:0;"><label>Rate %</label><input type="number" step="0.01" name="rate_percent" value="15"></div>
    <div class="form-group" style="margin:0;"><label><input type="checkbox" name="is_default" value="1" style="width:auto;display:inline-block;"> Default</label></div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card"><p>No tax rates yet — add your standard VAT rate above.</p></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name (English)</th><th>Name (Arabic)</th><th>Rate %</th><th>Default</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'tax-' . $r['id']; ?>
      <?php if (Auth::can('manage_business_setup')): ?>
        <form id="<?= $fid ?>" method="post" action="/app/business-setup/tax-rates/<?= $r['id'] ?>"><?= Csrf::field() ?></form>
      <?php endif; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="text" name="name" value="<?= View::e($r['name']) ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:160px;"></td>
        <td><input form="<?= $fid ?>" type="text" name="name_ar" dir="rtl" value="<?= View::e($r['name_ar'] ?? '') ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:160px;"></td>
        <td><input form="<?= $fid ?>" type="number" step="0.01" name="rate_percent" value="<?= View::e((string)$r['rate_percent']) ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="width:100px;"></td>
        <td><input form="<?= $fid ?>" type="checkbox" name="is_default" value="1" <?= $r['is_default'] ? 'checked' : '' ?> <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?>></td>
        <td style="display:flex;gap:6px;">
          <?php if (Auth::can('manage_business_setup')): ?>
            <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
            <form method="post" action="/app/business-setup/tax-rates/<?= $r['id'] ?>/delete" onsubmit="return confirm('Delete this tax rate?');" style="display:inline;">
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
