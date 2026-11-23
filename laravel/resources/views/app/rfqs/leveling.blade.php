@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/rfqs/<?= $rfq['id'] ?>">&larr; <?= e($rfq['title']) ?></a></p>
    <h1><?= t('user.rfqs.leveling_title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.rfqs.leveling_hint') ?></p>
  </div>
</div>

<?php if (empty($quoteColumns)): ?>
  <div class="card empty-state"><div class="icon">📊</div><h3><?= t('user.rfqs.leveling_no_quotes_title') ?></h3></div>
<?php else: ?>

  <div class="card" style="margin-bottom:20px;overflow-x:auto;">
    <table class="data">
      <thead>
        <tr>
          <th><?= t('user.rfqs.leveling_item_column') ?></th>
          <th><?= t('common.qty') ?></th>
          <?php foreach ($quoteColumns as $col): ?>
            <th><?= e($col['supplier_name']) ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['item']['description']) ?></td>
            <td><?= e((string) $row['item']['qty']) ?></td>
            <?php foreach ($quoteColumns as $col): ?>
              <?php $cell = $row['cells'][$col['id']]; ?>
              <td<?= $row['cheapest_quote_id'] === $col['id'] ? ' style="background:#eefbf0;"' : '' ?>>
                <?php if ($cell['total'] !== null): ?>
                  <?= money($cell['total']) ?>
                  <div class="help-text" style="font-size:11px;"><?= money($cell['unit_price']) ?> / <?= t('common.unit') ?></div>
                  <?php if ($row['cheapest_quote_id'] === $col['id']): ?>
                    <span class="badge badge-blue"><?= t('user.rfqs.cheapest_badge') ?></span>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="help-text" title="<?= e(t('user.rfqs.leveling_not_itemized_hint')) ?>">&mdash;</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="font-weight:600;background:var(--bg);">
          <td colspan="2"><?= t('user.rfqs.leveling_submitted_total_row') ?></td>
          <?php foreach ($quoteColumns as $col): ?>
            <td><?= money($col['total_amount']) ?></td>
          <?php endforeach; ?>
        </tr>
      </tfoot>
    </table>
  </div>

  <div class="card">
    <h3 style="font-size:14px;"><?= t('user.rfqs.leveling_best_combined_title') ?></h3>
    <?php if ($anyLineItemized): ?>
      <div class="total-row"><?= t('user.rfqs.leveling_best_combined_label') ?>: <?= money($bestCombinedTotal) ?></div>
      <p class="help-text" style="margin-top:8px;margin-bottom:0;"><?= t('user.rfqs.leveling_best_combined_hint') ?></p>
    <?php else: ?>
      <p class="help-text" style="margin:0;"><?= t('user.rfqs.leveling_best_combined_unavailable') ?></p>
    <?php endif; ?>
  </div>

<?php endif; ?>
@endsection
