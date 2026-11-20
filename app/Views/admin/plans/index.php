<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.plans.title') ?></h1>
  <a href="/admin/plans/create" class="btn btn-primary">+ <?= t('admin.plans.new') ?></a>
</div>

<table class="data">
  <thead><tr><th><?= t('common.name') ?></th><th><?= t('billing.monthly') ?></th><th><?= t('billing.yearly') ?></th><th><?= t('admin.plans.limits') ?></th><th><?= t('common.active') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($plans as $p): ?>
    <tr>
      <td><?= View::e($p['name']) ?> <span class="help-text">(<?= View::e($p['slug']) ?>)</span></td>
      <td><?= View::money((float)$p['price_monthly']) ?></td>
      <td><?= View::money((float)$p['price_yearly']) ?></td>
      <td class="help-text"><?= t('admin.plans.users_projects', ['users' => $p['max_users'], 'projects' => $p['max_projects']]) ?></td>
      <td><span class="badge badge-<?= $p['is_active'] ? 'green' : 'gray' ?>"><?= $p['is_active'] ? t('common.active') : t('admin.plans.hidden') ?></span></td>
      <td style="display:flex;gap:8px;">
        <a href="/admin/plans/<?= $p['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
        <form method="post" action="/admin/plans/<?= $p['id'] ?>/delete" onsubmit="return confirm('<?= t('admin.plans.delete_confirm') ?>');">
          <?= Csrf::field() ?>
          <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
