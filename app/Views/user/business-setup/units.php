<?php use App\Core\View; use App\Core\Csrf; use App\Core\Auth; ?>
<div class="page-head">
  <h1>Business Setup</h1>
</div>

<div class="tabs">
  <a href="/app/business-setup/building-types">Building Types</a>
  <a href="/app/business-setup/contact-types">Contact Types</a>
  <a href="/app/business-setup/client-types">Client Types</a>
  <a href="/app/business-setup/units-of-measure" class="active">Units of Measure</a>
  <a href="/app/business-setup/tax-rates">Tax Rates</a>
  <a href="/app/business-setup/compliance">Compliance Documents</a>
</div>

<?php if (Auth::can('manage_business_setup')): ?>
<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;">Units of Measure</h3>
  <form method="post" action="/app/business-setup/units-of-measure" class="form-row" style="align-items:end;grid-template-columns:120px 1fr 140px auto;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;"><label>Code</label><input type="text" name="code" placeholder="e.g. sqm" required></div>
    <div class="form-group" style="margin:0;"><label>Name</label><input type="text" name="name" placeholder="e.g. Square meter" required></div>
    <div class="form-group" style="margin:0;"><label>Sort order</label><input type="number" name="sort_order" value="0"></div>
    <button type="submit" class="btn btn-primary">Add</button>
  </form>
  <?php if (empty($rows)): ?>
    <form method="post" action="/app/business-setup/units-of-measure/load-defaults" style="margin-top:12px;">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-outline btn-sm">+ Load suggested defaults</button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($rows)): ?>
  <div class="empty-state card"><p>No units of measure yet.</p></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Code</th><th>Name</th><th>Sort order</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'unit-' . $r['id']; ?>
      <?php if (Auth::can('manage_business_setup')): ?>
        <form id="<?= $fid ?>" method="post" action="/app/business-setup/units-of-measure/<?= $r['id'] ?>"><?= Csrf::field() ?></form>
      <?php endif; ?>
      <tr>
        <td><input form="<?= $fid ?>" type="text" name="code" value="<?= View::e($r['code']) ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="width:100px;"></td>
        <td><input form="<?= $fid ?>" type="text" name="name" value="<?= View::e($r['name']) ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="min-width:200px;"></td>
        <td><input form="<?= $fid ?>" type="number" name="sort_order" value="<?= View::e((string)$r['sort_order']) ?>" <?= Auth::can('manage_business_setup') ? '' : 'disabled' ?> style="width:90px;"></td>
        <td style="display:flex;gap:6px;">
          <?php if (Auth::can('manage_business_setup')): ?>
            <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
            <form method="post" action="/app/business-setup/units-of-measure/<?= $r['id'] ?>/delete" onsubmit="return confirm('Delete this unit?');" style="display:inline;">
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
