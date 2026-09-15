<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('user.suppliers.title') ?></h1>
  <a href="/app/suppliers/create" class="btn btn-primary"><?= t('user.suppliers.new') ?></a>
</div>

<?php if (empty($suppliers)): ?>
  <div class="card empty-state">
    <div class="icon">🚚</div>
    <h3><?= t('user.suppliers.no_suppliers_title') ?></h3>
    <p><?= t('user.suppliers.no_suppliers_hint') ?></p>
    <a href="/app/suppliers/create" class="btn btn-primary"><?= t('user.suppliers.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.contact') ?></th><th><?= t('common.email') ?></th><th><?= t('common.phone') ?></th><th><?= t('common.category') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($suppliers as $s): ?>
      <tr>
        <td><?= View::e(View::local($s, 'name')) ?></td>
        <td><?= View::e($s['contact_name']) ?></td>
        <td><?= View::e($s['email']) ?></td>
        <td><?= View::e($s['phone']) ?></td>
        <td><?php if ($s['category']): ?><span class="badge badge-gray"><?= View::e($s['category']) ?></span><?php endif; ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/suppliers/<?= $s['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <form method="post" action="/app/suppliers/<?= $s['id'] ?>/delete" onsubmit="return confirm('<?= t('user.suppliers.remove_confirm') ?>');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
