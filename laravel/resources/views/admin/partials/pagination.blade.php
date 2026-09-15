<?php
/**
 * Shared pagination bar for admin list views: a 10/20/50-per-page selector plus prev/next
 * navigation. Expects a `$paginator` (Illuminate\Pagination\LengthAwarePaginator, e.g. from
 * ->paginate()->withQueryString()) so page links keep every existing filter/search/sort the
 * caller's query already applies. Optional `$perPageOptions` overrides the default choices.
 */
$perPageOptions = $perPageOptions ?? [10, 20, 50];
$preserved = request()->except(['page', 'per_page']);
?>
<?php if ($paginator->total() > 0): ?>
<div class="pagination-bar" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-top:16px;">
  <p class="help-text" style="margin:0;">
    <?= t('admin.pagination.showing', ['from' => $paginator->firstItem(), 'thru' => $paginator->lastItem(), 'total' => $paginator->total()]) ?>
  </p>

  <div style="display:flex;align-items:center;gap:18px;flex-wrap:wrap;">
    <form method="get" style="display:flex;align-items:center;gap:8px;margin:0;">
      <?php foreach ($preserved as $key => $value): ?>
        <?php if (!is_array($value)): ?>
          <input type="hidden" name="<?= e($key) ?>" value="<?= e($value) ?>">
        <?php endif; ?>
      <?php endforeach; ?>
      <label class="help-text" style="margin:0;white-space:nowrap;" for="pagination-per-page"><?= t('admin.pagination.show') ?></label>
      <select name="per_page" id="pagination-per-page" onchange="this.form.submit()" style="width:auto;padding:6px 10px;">
        <?php foreach ($perPageOptions as $opt): ?>
          <option value="<?= (int) $opt ?>" <?= $paginator->perPage() === (int) $opt ? 'selected' : '' ?>><?= (int) $opt ?></option>
        <?php endforeach; ?>
      </select>
      <span class="help-text" style="white-space:nowrap;"><?= t('admin.pagination.per_page_suffix') ?></span>
    </form>

    <?php if ($paginator->lastPage() > 1): ?>
      <div style="display:flex;align-items:center;gap:6px;">
        <?php if ($paginator->onFirstPage()): ?>
          <span class="btn btn-sm btn-light" style="opacity:.5;pointer-events:none;">‹ <?= t('admin.pagination.prev') ?></span>
        <?php else: ?>
          <a href="<?= e($paginator->previousPageUrl()) ?>" class="btn btn-sm btn-light">‹ <?= t('admin.pagination.prev') ?></a>
        <?php endif; ?>

        <span class="help-text" style="padding:0 6px;white-space:nowrap;"><?= t('admin.pagination.page_of', ['page' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) ?></span>

        <?php if ($paginator->hasMorePages()): ?>
          <a href="<?= e($paginator->nextPageUrl()) ?>" class="btn btn-sm btn-light"><?= t('admin.pagination.next') ?> ›</a>
        <?php else: ?>
          <span class="btn btn-sm btn-light" style="opacity:.5;pointer-events:none;"><?= t('admin.pagination.next') ?> ›</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
