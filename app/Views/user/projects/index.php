<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= t('user.projects.title') ?> <?php if ($projectLimit !== null && $projectLimit < 999): ?><span class="badge badge-<?= $withinProjectLimit ? 'gray' : 'red' ?>"><?= count($projects) ?> / <?= $projectLimit ?></span><?php endif; ?></h1>
  <a href="/app/projects/create" class="btn btn-primary <?= $withinProjectLimit ? '' : 'disabled' ?>" <?= $withinProjectLimit ? '' : 'onclick="return false;" style="opacity:.5;cursor:not-allowed;"' ?>><?= t('user.projects.new') ?></a>
</div>

<?php if (empty($projects)): ?>
  <div class="card empty-state">
    <div class="icon">🏗️</div>
    <h3><?= t('user.projects.no_projects_title') ?></h3>
    <p><?= t('user.projects.no_projects_hint') ?></p>
    <a href="/app/projects/create" class="btn btn-primary"><?= t('user.projects.new') ?></a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.project') ?></th><th><?= t('common.client') ?></th><th><?= t('common.status') ?></th><th><?= t('common.amount') ?></th><th><?= t('user.projects.dates_col') ?></th></tr></thead>
    <tbody>
    <?php foreach ($projects as $p): ?>
      <tr>
        <td><a href="/app/projects/<?= $p['id'] ?>"><?= View::e(View::local($p, 'name')) ?></a></td>
        <td><?= View::e($p['client_name'] ? View::local($p, 'client_name') : '—') ?></td>
        <td><span class="badge badge-blue"><?= View::e(str_replace('_',' ',$p['status'])) ?></span></td>
        <td><?= View::money((float)$p['budget']) ?></td>
        <td class="help-text"><?= View::e($p['start_date']) ?> → <?= View::e($p['end_date']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
