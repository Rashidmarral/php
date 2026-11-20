@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.materials.library.title') ?></h1>
  <a href="/app/materials" class="btn btn-light">← <?= t('user.materials.back_to_materials') ?></a>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('user.materials.library.hint') ?></p>

<?php if (empty($items)): ?>
  <div class="card empty-state">
    <div class="icon">📦</div>
    <h3><?= t('user.materials.library.empty_title') ?></h3>
    <p><?= t('user.materials.library.empty_hint') ?></p>
  </div>
<?php else: ?>
  <form method="post" action="/app/materials/library/import" id="library-import-form">
    <?= csrf_field() ?>
    <div style="display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center;">
      <input type="text" id="library-search" placeholder="<?= t('user.materials.search_placeholder') ?>" style="flex:1;min-width:220px;">
      <select id="library-category-filter">
        <option value=""><?= t('user.materials.all_categories') ?> (<?= count($items) ?>)</option>
        <?php foreach ($categories as $cat => $count): ?>
          <option value="<?= e(strtolower($cat)) ?>"><?= e($cat) ?> (<?= $count ?>)</option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary"><?= t('user.materials.library.import_selected') ?></button>
    </div>

    <div style="overflow-x:auto;">
    <table class="data" id="library-table">
      <thead><tr>
        <th><input type="checkbox" id="library-select-all"></th>
        <th><?= t('common.description') ?></th><th><?= t('common.category') ?></th><th><?= t('common.unit') ?></th>
        <th><?= t('user.materials.material_col') ?></th><th><?= t('user.materials.labor_col') ?></th><th><?= t('common.rate') ?></th>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
        <tr data-category="<?= e(strtolower($it['category'])) ?>" data-search="<?= e(strtolower($it['name'] . ' ' . ($it['sku'] ?? ''))) ?>">
          <td><input type="checkbox" name="item_ids[]" value="<?= $it['id'] ?>" class="library-item-checkbox"></td>
          <td><?= e(local($it, 'name')) ?><?php if (!empty($it['sku'])): ?><br><span class="help-text"><?= e($it['sku']) ?></span><?php endif; ?></td>
          <td><span class="badge badge-gray"><?= e($it['category']) ?></span></td>
          <td><?= e($it['unit']) ?></td>
          <td><?= money((float) $it['material_cost']) ?></td>
          <td><?= money((float) $it['labor_cost']) ?></td>
          <td><strong><?= money((float) $it['material_cost'] + (float) $it['labor_cost']) ?></strong>/<?= e($it['unit']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  </form>

  <script>
  (function() {
    const search = document.getElementById('library-search');
    const categoryFilter = document.getElementById('library-category-filter');
    const selectAll = document.getElementById('library-select-all');
    const rows = document.querySelectorAll('#library-table tbody tr');
    function apply() {
      const q = search.value.toLowerCase();
      const cat = categoryFilter.value;
      rows.forEach(tr => {
        const matchesSearch = tr.dataset.search.includes(q);
        const matchesCategory = !cat || tr.dataset.category === cat;
        tr.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
      });
    }
    search.addEventListener('input', apply);
    categoryFilter.addEventListener('change', apply);
    selectAll.addEventListener('change', function() {
      document.querySelectorAll('.library-item-checkbox').forEach(cb => {
        if (cb.closest('tr').style.display !== 'none') {
          cb.checked = selectAll.checked;
        }
      });
    });
  })();
  </script>
<?php endif; ?>

@endsection
