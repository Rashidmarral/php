@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('user.team.title') ?></h1>
  <div style="display:flex;align-items:center;gap:10px;">
    <?php if ($userLimit !== null && $userLimit < 999): ?>
      <span class="badge badge-<?= $withinUserLimit ? 'gray' : 'red' ?>"><?= count($members) ?> / <?= $userLimit ?> <?= t('user.team.members_suffix') ?></span>
    <?php endif; ?>
    <?php if (auth()->user()->can('manage_team')): ?>
      <a href="/app/team/export-wps.csv" class="btn btn-light">⬇ <?= t('user.team.export_wps') ?></a>
    <?php endif; ?>
  </div>
</div>

<?php if (auth()->user()->can('manage_team')): ?>
  <p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
    <?= t('user.team.payroll_ready', ['ready' => $payrollReadyCount, 'total' => count($members)]) ?>
  </p>
<?php endif; ?>

<?php if (auth()->user()->can('manage_team')): ?>
<div class="card" style="margin-bottom:24px;">
  <h3><?= t('user.team.invite_member') ?></h3>
  <?php if (!$withinUserLimit): ?>
    <div class="alert alert-error"><?= t('user.team.limit_reached', ['limit' => $userLimit]) ?> <a href="/app/billing"><?= t('user.team.upgrade_plan') ?></a></div>
  <?php else: ?>
  <form method="post" action="/app/team" class="form-row" style="align-items:end;grid-template-columns:1fr 1fr 1fr auto;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('common.name') ?></label><input type="text" name="name" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.email') ?></label><input type="email" name="email" required></div>
    <div class="form-group" style="margin:0;">
      <label><?= t('common.role') ?></label>
      <select name="role">
        <?php foreach (\App\Models\User::ASSIGNABLE_ROLES as $key => $label): ?>
          <option value="<?= e($key) ?>" <?= $key === 'estimator' ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="btn btn-primary"><?= t('common.invite') ?></button>
  </form>
  <p class="help-text" style="margin-top:10px;margin-bottom:0;">
    <?= t('user.team.roles_hint') ?>
  </p>
  <?php endif; ?>
</div>
<?php endif; ?>

<table class="data">
  <thead><tr><th><?= t('common.name') ?></th><th><?= t('common.email') ?></th><th><?= t('common.role') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $fid = 'role-' . $m['id']; ?>
    <?php if (auth()->user()->can('manage_team') && $m['role'] !== 'owner'): ?>
      <form id="<?= $fid ?>" method="post" action="/app/team/<?= $m['id'] ?>/role"><?= csrf_field() ?></form>
    <?php endif; ?>
    <tr>
      <td><?= e($m['name']) ?><?php if ((int)$m['id'] === (int)auth()->id()): ?> <span class="help-text"><?= t('user.team.you') ?></span><?php endif; ?></td>
      <td><?= e($m['email']) ?></td>
      <td>
        <?php if (auth()->user()->can('manage_team') && $m['role'] !== 'owner'): ?>
          <select form="<?= $fid ?>" name="role" onchange="this.form.requestSubmit()" style="width:auto;display:inline-block;padding:4px 8px;font-size:12.5px;">
            <?php foreach (\App\Models\User::ASSIGNABLE_ROLES as $key => $label): ?>
              <option value="<?= e($key) ?>" <?= $m['role'] === $key ? 'selected' : '' ?>><?= e(\App\Models\User::ROLE_LABELS[$key] ?? ucfirst($key)) ?></option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <span class="badge badge-blue"><?= e(\App\Models\User::ROLE_LABELS[$m['role']] ?? ucfirst($m['role'])) ?></span>
        <?php endif; ?>
      </td>
      <td><span class="badge badge-green"><?= e($m['status']) ?></span></td>
      <td style="display:flex;gap:8px;">
        <?php if (auth()->user()->can('manage_team')): ?>
        <a href="/app/team/<?= $m['id'] ?>/payroll" class="btn btn-sm btn-light"><?= t('user.team_payroll.nav_link') ?></a>
        <?php endif; ?>
        <a href="/app/team/<?= $m['id'] ?>/documents" class="btn btn-sm btn-light"><?= t('user.team_docs.nav_link') ?></a>
        <?php if (auth()->user()->can('manage_team') && (int)$m['id'] !== (int)auth()->id() && $m['role'] !== 'owner'): ?>
        <details style="display:inline-block;">
          <summary class="btn btn-sm btn-light" style="cursor:pointer;display:inline-block;"><?= t('user.team_permissions.nav_link') ?></summary>
          <div class="card" style="margin-top:8px;min-width:320px;">
            <p class="help-text" style="margin-top:0;"><?= t('user.team_permissions.hint') ?></p>
            <form method="post" action="/app/team/<?= $m['id'] ?>/permissions">
              <?= csrf_field() ?>
              <?php foreach (\App\Http\Controllers\App\TeamController::OVERRIDABLE_ABILITIES as $ability):
                $current = $m['permission_overrides'][$ability] ?? null;
                $selected = $current === true ? 'allow' : ($current === false ? 'deny' : 'default');
              ?>
                <div class="form-group" style="margin-bottom:8px;">
                  <label><?= t('user.team_permissions.ability_' . $ability) ?></label>
                  <select name="overrides[<?= $ability ?>]" style="width:auto;display:inline-block;padding:4px 8px;font-size:12.5px;">
                    <option value="default" <?= $selected === 'default' ? 'selected' : '' ?>><?= t('common.default') ?></option>
                    <option value="allow" <?= $selected === 'allow' ? 'selected' : '' ?>><?= t('user.team_permissions.option_allow') ?></option>
                    <option value="deny" <?= $selected === 'deny' ? 'selected' : '' ?>><?= t('user.team_permissions.option_deny') ?></option>
                  </select>
                </div>
              <?php endforeach; ?>
              <button type="submit" class="btn btn-primary btn-sm"><?= t('common.save') ?></button>
            </form>
          </div>
        </details>
        <?php endif; ?>
        <?php if (auth()->user()->can('manage_team') && (int)$m['id'] !== (int)auth()->id() && $m['role'] !== 'owner'): ?>
        <form method="post" action="/app/team/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= t('user.team.remove_member_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-sm btn-light"><?= t('common.remove') ?></button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

@endsection
