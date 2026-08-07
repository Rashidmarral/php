<?php use App\Core\View; ?>
<div class="page-head">
  <h1><?= t('admin.usage.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('admin.usage.hint') ?></p>

<table class="data">
  <thead><tr><th><?= t('common.company') ?></th><th><?= t('common.plan') ?></th><th><?= t('common.status') ?></th><th><?= t('admin.usage.users') ?></th><th><?= t('admin.usage.projects') ?></th></tr></thead>
  <tbody>
  <?php foreach ($usage as $u): ?>
    <?php
      $barColor = fn(?float $pct) => $pct === null ? 'var(--border)' : ($pct >= 100 ? '#c0392b' : ($pct >= 80 ? '#e8912b' : 'var(--brand)'));
    ?>
    <tr>
      <td><a href="/admin/companies/<?= $u['id'] ?>"><?= View::e($u['name']) ?></a></td>
      <td><?= View::e($u['plan_name'] ?? '—') ?></td>
      <td><span class="badge badge-<?= $u['status']==='active'?'green':($u['status']==='suspended'?'red':'yellow') ?>"><?= View::e($u['status']) ?></span></td>
      <td>
        <?php if ($u['user_pct'] === null): ?>
          <?= (int) $u['user_count'] ?> / <?= t('admin.usage.unlimited') ?>
        <?php else: ?>
          <div style="font-size:12.5px;margin-bottom:3px;"><?= (int) $u['user_count'] ?> / <?= (int) $u['max_users'] ?> (<?= (int) $u['user_pct'] ?>%)</div>
          <div style="background:var(--bg);border-radius:4px;height:6px;width:120px;overflow:hidden;"><div style="width:<?= min(100, $u['user_pct']) ?>%;height:100%;background:<?= $barColor($u['user_pct']) ?>;"></div></div>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($u['project_pct'] === null): ?>
          <?= (int) $u['project_count'] ?> / <?= t('admin.usage.unlimited') ?>
        <?php else: ?>
          <div style="font-size:12.5px;margin-bottom:3px;"><?= (int) $u['project_count'] ?> / <?= (int) $u['max_projects'] ?> (<?= (int) $u['project_pct'] ?>%)</div>
          <div style="background:var(--bg);border-radius:4px;height:6px;width:120px;overflow:hidden;"><div style="width:<?= min(100, $u['project_pct']) ?>%;height:100%;background:<?= $barColor($u['project_pct']) ?>;"></div></div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($usage)): ?>
    <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;"><?= t('admin.usage.no_companies') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>
