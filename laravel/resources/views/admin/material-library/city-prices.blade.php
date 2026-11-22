@extends('layouts.admin')

@section('content')
<div class="page-head">
  <h1><?= t('admin.material_library.city_prices_title') ?></h1>
  <a href="/admin/material-library" class="btn btn-light">← <?= t('admin.material_library.title') ?></a>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.material_library.city_prices_hint') ?></p>

<div class="card" style="margin-bottom:24px;">
  <form method="get" action="/admin/material-library/city-prices" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <div class="form-group" style="margin-bottom:0;min-width:260px;">
      <label><?= t('common.city') ?></label>
      <select name="city" class="city-select" data-placeholder="<?= e(t('user.settings.city_search_placeholder')) ?>" data-no-results="<?= e(t('user.settings.city_no_results')) ?>" onchange="this.form.submit()">
        <?php foreach ($cities as $c): ?>
          <option value="<?= e($c['en']) ?>" data-ar="<?= e($c['ar']) ?>" <?= $city === $c['en'] ? 'selected' : '' ?>><?= e($c['ar'] !== '' ? $c['en'] . ' / ' . $c['ar'] : $c['en']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-light"><?= t('common.filter') ?></button>
  </form>
</div>

<?php if ($city === ''): ?>
  <div class="card empty-state">
    <h3><?= t('admin.material_library.city_prices_pick_city') ?></h3>
  </div>
<?php else: ?>
  <div class="card" style="margin-bottom:24px;">
    <h3><?= t('admin.material_library.city_price_import_title') ?></h3>
    <p class="help-text" style="margin-top:-4px;"><?= t('admin.material_library.city_price_import_hint') ?></p>
    <form method="post" action="/admin/material-library/city-prices/import" enctype="multipart/form-data" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
      <?= csrf_field() ?>
      <input type="hidden" name="city" value="<?= e($city) ?>">
      <input type="file" name="csv" accept=".csv,text/csv" required>
      <button type="submit" class="btn btn-light"><?= t('admin.material_library.city_price_import_button') ?></button>
    </form>
  </div>

  <form method="post" action="/admin/material-library/city-prices">
    <?= csrf_field() ?>
    <input type="hidden" name="city" value="<?= e($city) ?>">

    <div style="overflow-x:auto;">
    <table class="data">
      <thead><tr>
        <th><?= t('user.materials.sku') ?></th>
        <th><?= t('common.name') ?></th>
        <th><?= t('common.category') ?></th>
        <th><?= t('common.unit') ?></th>
        <th><?= t('admin.material_library.base_price') ?></th>
        <th><?= t('admin.material_library.city_price_col', ['city' => $city]) ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $it): $override = $overrides[$it->id] ?? null; ?>
        <tr>
          <td><?= e($it->sku ?? '') ?></td>
          <td><?= e(local($it, 'name')) ?></td>
          <td><span class="badge badge-gray"><?= e($it->category) ?></span></td>
          <td><?= e($it->unit) ?></td>
          <td><?= money((float) $it->material_cost) ?></td>
          <td>
            <input type="number" step="0.01" min="0" name="prices[<?= $it->id ?>]"
                   value="<?= $override !== null ? e((string) $override) : '' ?>"
                   placeholder="<?= e((string) $it->material_cost) ?>" style="width:110px;">
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="6" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.material_library.none_yet') ?></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
    </div>

    <div style="margin-top:14px;">
      <button type="submit" class="btn btn-primary"><?= t('admin.material_library.city_prices_save') ?></button>
    </div>
  </form>
<?php endif; ?>
@endsection
