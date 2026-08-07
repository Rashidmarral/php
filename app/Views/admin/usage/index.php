<?php use App\Core\View; ?>
<div class="page-head">
  <h1>Platform Usage</h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">How close every company is to their plan's user and project limits — companies near or over a limit are shown first, they're your best upgrade conversations.</p>

<table class="data">
  <thead><tr><th>Company</th><th>Plan</th><th>Status</th><th>Users</th><th>Projects</th></tr></thead>
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
          <?= (int) $u['user_count'] ?> / unlimited
        <?php else: ?>
          <div style="font-size:12.5px;margin-bottom:3px;"><?= (int) $u['user_count'] ?> / <?= (int) $u['max_users'] ?> (<?= (int) $u['user_pct'] ?>%)</div>
          <div style="background:var(--bg);border-radius:4px;height:6px;width:120px;overflow:hidden;"><div style="width:<?= min(100, $u['user_pct']) ?>%;height:100%;background:<?= $barColor($u['user_pct']) ?>;"></div></div>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($u['project_pct'] === null): ?>
          <?= (int) $u['project_count'] ?> / unlimited
        <?php else: ?>
          <div style="font-size:12.5px;margin-bottom:3px;"><?= (int) $u['project_count'] ?> / <?= (int) $u['max_projects'] ?> (<?= (int) $u['project_pct'] ?>%)</div>
          <div style="background:var(--bg);border-radius:4px;height:6px;width:120px;overflow:hidden;"><div style="width:<?= min(100, $u['project_pct']) ?>%;height:100%;background:<?= $barColor($u['project_pct']) ?>;"></div></div>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (empty($usage)): ?>
    <tr><td colspan="5" class="help-text" style="text-align:center;padding:20px;">No companies yet.</td></tr>
  <?php endif; ?>
  </tbody>
</table>
