<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <a href="/app/estimates/new" class="help-text">← Back</a>
    <h1 style="margin-top:6px;display:flex;align-items:center;gap:10px;"><span><?= View::e($template['icon']) ?></span> <?= View::e($template['name_en']) ?></h1>
    <p class="help-text" style="margin-top:2px;"><?= View::e($template['description_en']) ?></p>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">
  <div class="card" style="padding:0;overflow:hidden;">
    <table class="data" style="border:none;">
      <thead><tr><th>Description</th><th>Type</th><th>Qty</th><th>UOM</th><th>Unit Cost</th></tr></thead>
      <tbody>
      <?php $lastSection = null; foreach ($items as $it): ?>
        <?php if ($it['section_number'] !== $lastSection): $lastSection = $it['section_number']; ?>
          <tr style="background:#fafcfb;"><td colspan="5"><strong><?= View::e($it['section_number']) ?> <?= View::e($it['section_title_en']) ?></strong></td></tr>
        <?php endif; ?>
        <tr>
          <td><?= View::e($it['item_number']) ?> <?= View::e($it['description_en']) ?></td>
          <td><span class="badge badge-<?= $it['item_type'] === 'labor' ? 'yellow' : 'gray' ?>"><?= ucfirst($it['item_type']) ?></span></td>
          <td>0 <span class="help-text">(<?= View::e(rtrim(rtrim(number_format((float)$it['default_qty'], 2), '0'), '.')) ?>)</span></td>
          <td><?= View::e($it['uom']) ?></td>
          <td><?= View::money((float)$it['unit_cost']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" action="/app/estimates/templates/<?= $template['id'] ?>" class="card" style="position:sticky;top:90px;">
    <?= Csrf::field() ?>
    <h3 style="font-size:14px;">Configuration</h3>

    <div class="form-group">
      <label><input type="checkbox" name="include_quantities" value="1" style="width:auto;display:inline-block;"> Include template quantities</label>
      <p class="help-text" style="margin-top:2px;">Otherwise quantities start at 0 and you fill them in.</p>
    </div>

    <div class="form-group"><label>Description</label><input type="text" name="title" value="<?= View::e($template['name_en']) ?>"></div>

    <div class="form-group">
      <label>Building Type</label>
      <select name="building_type">
        <option value="">Building type…</option>
        <?php foreach ($buildingTypes as $bt): ?>
          <option value="<?= View::e($bt['name']) ?>" <?= $bt['name'] === $template['building_type'] ? 'selected' : '' ?>><?= View::e($bt['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if (empty($buildingTypes)): ?><p class="help-text">No building types yet — add some in <a href="/app/business-setup/building-types" target="_blank">Business Setup</a>.</p><?php endif; ?>
    </div>

    <div class="form-group"><label>Job address or suburb</label><input type="text" name="job_address" placeholder="Job address or suburb…"></div>

    <div class="form-group">
      <label>Client</label>
      <select name="client_id">
        <option value="">Client name…</option>
        <?php foreach ($clients as $c): ?><option value="<?= $c['id'] ?>"><?= View::e($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>

    <div class="total-row" style="font-size:15px;">Template value: <?= View::money($subtotal) ?></div>

    <div style="display:flex;gap:8px;margin-top:14px;">
      <a href="/app/estimates/new" class="btn btn-light" style="flex:1;text-align:center;">Cancel</a>
      <button type="submit" class="btn btn-primary" style="flex:2;">Create estimate</button>
    </div>
  </form>
</div>
