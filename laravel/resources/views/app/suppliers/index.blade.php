@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.suppliers.title') ?></h1>
  <a href="/app/suppliers/create" class="btn btn-primary"><?= t('user.suppliers.new') ?></a>
</div>

<form method="get" action="/app/suppliers" style="margin-bottom:16px;">
  <label style="display:inline-flex;align-items:center;gap:6px;font-size:13px;">
    <input type="checkbox" name="approved_only" value="1" onchange="this.form.submit()" <?= $approvedOnly ? 'checked' : '' ?>>
    <?= t('user.suppliers.approved_only_filter') ?>
  </label>
</form>

<?php if (empty($suppliers)): ?>
  <div class="card empty-state">
    <div class="icon">🚚</div>
    <h3><?= t('user.suppliers.no_suppliers_title') ?></h3>
    <p><?= t('user.suppliers.no_suppliers_hint') ?></p>
    <a href="/app/suppliers/create" class="btn btn-primary"><?= t('user.suppliers.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.contact') ?></th><th><?= t('common.email') ?></th><th><?= t('common.phone') ?></th><th><?= t('common.category') ?></th><th><?= t('user.suppliers.currency') ?></th><th><?= t('user.suppliers.classification_grade_col') ?></th><th><?= t('user.suppliers.rating_col') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($suppliers as $s): ?>
      <tr>
        <td>
          <a href="/app/suppliers/<?= $s['id'] ?>"><?= e(local($s, 'name')) ?></a>
          <?php if ($s['is_approved_vendor']): ?> <span class="badge badge-green"><?= t('user.suppliers.approved_vendor') ?></span><?php endif; ?>
        </td>
        <td><?= e($s['contact_name']) ?></td>
        <td><?= e($s['email']) ?></td>
        <td><?= e($s['phone']) ?></td>
        <td><?php if ($s['category']): ?><span class="badge badge-gray"><?= e($s['category']) ?></span><?php endif; ?></td>
        <td><?php if (($s['currency'] ?? 'SAR') !== 'SAR'): ?><span class="badge badge-gray"><?= e($s['currency']) ?></span><?php else: ?>—<?php endif; ?></td>
        <td><?php if ($s['classification_grade']): ?><span class="badge badge-gray"><?= t('user.settings.classification_grade_option', ['n' => $s['classification_grade']]) ?></span><?php endif; ?></td>
        <td><?= $s['average_rating'] !== null ? str_repeat('★', (int) round($s['average_rating'])) . str_repeat('☆', 5 - (int) round($s['average_rating'])) . ' (' . $s['average_rating'] . ')' : '—' ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/suppliers/<?= $s['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a>
          <a href="/app/suppliers/<?= $s['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <form method="post" action="/app/suppliers/<?= $s['id'] ?>/delete" onsubmit="return confirm('<?= t('user.suppliers.remove_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

@endsection
