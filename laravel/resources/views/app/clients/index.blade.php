@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.clients.title') ?></h1>
  <a href="/app/clients/create" class="btn btn-primary"><?= t('user.clients.new') ?></a>
</div>

<?php if (empty($clients)): ?>
  <div class="card empty-state">
    <div class="icon">👥</div>
    <h3><?= t('user.clients.no_clients_title') ?></h3>
    <p><?= t('user.clients.no_clients_hint') ?></p>
    <a href="/app/clients/create" class="btn btn-primary"><?= t('user.clients.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.email') ?></th><th><?= t('common.phone') ?></th><th><?= t('user.clients.portal_col') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <tr>
        <td><?= e(local($c, 'name')) ?></td>
        <td><?= e($c['email']) ?></td>
        <td><?= e($c['phone']) ?></td>
        <td>
          <?php if (!empty($c['portal_enabled'])): ?>
            <span class="badge badge-green"><?= t('common.enabled') ?></span>
          <?php else: ?>
            <span class="badge badge-gray"><?= t('common.disabled') ?></span>
          <?php endif; ?>
        </td>
        <td style="display:flex;gap:8px;">
          <a href="/app/clients/<?= $c['id'] ?>/edit" class="btn btn-sm btn-light"><?= t('common.edit') ?></a>
          <?php if (!empty($c['portal_enabled'])): ?>
            <form method="post" action="/app/clients/<?= $c['id'] ?>/disable-portal">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-light"><?= t('user.clients.disable_portal') ?></button>
            </form>
          <?php else: ?>
            <form method="post" action="/app/clients/<?= $c['id'] ?>/enable-portal">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.clients.enable_portal') ?></button>
            </form>
          <?php endif; ?>
          <form method="post" action="/app/clients/<?= $c['id'] ?>/delete" onsubmit="return confirm('<?= t('user.clients.remove_confirm') ?>');">
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
