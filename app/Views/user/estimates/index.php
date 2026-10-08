<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= t('user.estimates.title') ?></h1>
  <a href="/app/estimates/new" class="btn btn-primary"><?= t('user.estimates.new') ?></a>
</div>

<?php if (empty($estimates)): ?>
  <div class="card empty-state">
    <div class="icon">🧾</div>
    <h3><?= t('user.estimates.no_estimates_title') ?></h3>
    <p><?= t('user.estimates.no_estimates_hint') ?></p>
    <a href="/app/estimates/new" class="btn btn-primary"><?= t('user.estimates.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead>
    <tbody>
    <?php foreach ($estimates as $e): ?>
      <tr>
        <td><a href="/app/estimates/<?= $e['id'] ?>"><?= View::e(View::local($e, 'title')) ?></a></td>
        <td><?= View::e($e['client_name'] ?? '—') ?></td>
        <td><span class="badge badge-<?= ['accepted'=>'green','declined'=>'red','sent'=>'blue'][$e['status']] ?? 'gray' ?>"><?= View::e($e['status']) ?></span></td>
        <td><?= View::money((float)$e['total']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
