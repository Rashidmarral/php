<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('user.consultations.title') ?></h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;"><?= t('user.consultations.hint') ?></p>

<?php if ($quota <= 0): ?>
  <div class="card" style="max-width:560px;background:var(--brand-light);">
    <h3 style="margin-bottom:6px;"><?= t('user.consultations.not_included') ?></h3>
    <p class="help-text"><?= t('user.consultations.not_included_hint') ?></p>
    <a href="/app/billing" class="btn btn-primary" style="margin-top:8px;"><?= t('user.consultations.view_plans') ?></a>
  </div>
<?php else: ?>
  <div class="card" style="max-width:560px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <strong><?= $remaining ?></strong> <?= t('user.consultations.remaining_of') ?> <strong><?= $quota ?></strong> <?= t('user.consultations.remaining_this_month') ?>
        <?php if ($planName): ?><span class="help-text">(<?= View::e($planName) ?> plan)</span><?php endif; ?>
      </div>
    </div>
    <div style="background:var(--bg);border-radius:4px;height:6px;margin-top:10px;overflow:hidden;">
      <div style="width:<?= $quota > 0 ? min(100, round(($used / $quota) * 100)) : 0 ?>%;height:100%;background:var(--brand);"></div>
    </div>
  </div>

  <?php if ($remaining > 0): ?>
    <div class="card" style="max-width:560px;margin-bottom:24px;">
      <h3><?= t('user.consultations.request_a_consultation') ?></h3>
      <form method="post" action="/app/consultations">
        <?= Csrf::field() ?>
        <div class="form-group">
          <label><?= t('user.consultations.format') ?></label>
          <select name="type">
            <option value="chat"><?= t('user.consultations.chat_video') ?></option>
            <option value="in_person"><?= t('user.consultations.in_person') ?></option>
          </select>
        </div>
        <div class="form-group"><label><?= t('user.consultations.what_discuss') ?></label><input type="text" name="topic" required placeholder="e.g. Pricing a villa foundation in rocky soil"></div>
        <div class="form-group"><label><?= t('user.consultations.additional_notes') ?></label><textarea name="notes" rows="3"></textarea></div>
        <div class="form-group"><label><?= t('user.consultations.preferred_date') ?></label><input type="date" name="preferred_date"></div>
        <button type="submit" class="btn btn-primary"><?= t('user.consultations.send_request') ?></button>
      </form>
    </div>
  <?php else: ?>
    <div class="card" style="max-width:560px;margin-bottom:24px;">
      <p class="help-text"><?= t('user.consultations.used_all') ?> <a href="/app/billing"><?= t('user.consultations.upgrade_link') ?></a> <?= t('user.consultations.for_higher_allowance') ?></p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <h3><?= t('user.consultations.your_requests') ?></h3>
  <?php if (empty($consultations)): ?>
    <p class="help-text"><?= t('user.consultations.none_yet') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('user.consultations.requested_col') ?></th><th><?= t('user.consultations.format') ?></th><th><?= t('user.consultations.topic_col') ?></th><th><?= t('common.status') ?></th><th><?= t('user.consultations.engineer_col') ?></th><th><?= t('user.consultations.scheduled_col') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($consultations as $c): ?>
        <tr>
          <td class="help-text"><?= View::e($c['created_at']) ?></td>
          <td><?= $c['type'] === 'in_person' ? t('user.consultations.in_person_label') : t('user.consultations.chat_video_label') ?></td>
          <td><?= View::e($c['topic']) ?></td>
          <td><span class="badge badge-<?= $c['status']==='completed'?'green':($c['status']==='cancelled'?'red':($c['status']==='scheduled'?'blue':'yellow')) ?>"><?= View::e(ucfirst($c['status'])) ?></span></td>
          <td><?= View::e($c['assigned_engineer'] ?: '—') ?></td>
          <td class="help-text"><?= View::e($c['scheduled_at'] ?: ($c['preferred_date'] ?: '—')) ?></td>
          <td>
            <?php if (in_array($c['status'], ['requested', 'scheduled'], true)): ?>
              <form method="post" action="/app/consultations/<?= $c['id'] ?>/cancel" onsubmit="return confirm('<?= t('user.consultations.cancel_confirm') ?>');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-light"><?= t('common.cancel') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
