<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Expert Consultation</h1>
</div>
<p class="help-text" style="margin-top:-12px;margin-bottom:20px;">Talk to one of our construction estimating engineers — by chat/video call or an in-person site visit — to sanity-check a tricky estimate or a pricing decision.</p>

<?php if ($quota <= 0): ?>
  <div class="card" style="max-width:560px;background:var(--brand-light);">
    <h3 style="margin-bottom:6px;">Not included in your plan</h3>
    <p class="help-text">Live expert consultations are a plan add-on. Upgrade to get access — Starter includes 1/month, Professional 3/month, Enterprise 5/month.</p>
    <a href="/app/billing" class="btn btn-primary" style="margin-top:8px;">View plans</a>
  </div>
<?php else: ?>
  <div class="card" style="max-width:560px;margin-bottom:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;">
      <div>
        <strong><?= $remaining ?></strong> of <strong><?= $quota ?></strong> consultation<?= $quota === 1 ? '' : 's' ?> remaining this month
        <?php if ($planName): ?><span class="help-text">(<?= View::e($planName) ?> plan)</span><?php endif; ?>
      </div>
    </div>
    <div style="background:var(--bg);border-radius:4px;height:6px;margin-top:10px;overflow:hidden;">
      <div style="width:<?= $quota > 0 ? min(100, round(($used / $quota) * 100)) : 0 ?>%;height:100%;background:var(--brand);"></div>
    </div>
  </div>

  <?php if ($remaining > 0): ?>
    <div class="card" style="max-width:560px;margin-bottom:24px;">
      <h3>Request a consultation</h3>
      <form method="post" action="/app/consultations">
        <?= Csrf::field() ?>
        <div class="form-group">
          <label>Format</label>
          <select name="type">
            <option value="chat">💬 Online chat / video call</option>
            <option value="in_person">🚗 In-person site visit</option>
          </select>
        </div>
        <div class="form-group"><label>What would you like to discuss?</label><input type="text" name="topic" required placeholder="e.g. Pricing a villa foundation in rocky soil"></div>
        <div class="form-group"><label>Additional notes (optional)</label><textarea name="notes" rows="3"></textarea></div>
        <div class="form-group"><label>Preferred date</label><input type="date" name="preferred_date"></div>
        <button type="submit" class="btn btn-primary">Send request</button>
      </form>
    </div>
  <?php else: ?>
    <div class="card" style="max-width:560px;margin-bottom:24px;">
      <p class="help-text">You've used all your consultations for this month. More become available next month, or <a href="/app/billing">upgrade your plan</a> for a higher allowance.</p>
    </div>
  <?php endif; ?>
<?php endif; ?>

<div class="card">
  <h3>Your requests</h3>
  <?php if (empty($consultations)): ?>
    <p class="help-text">No consultations requested yet.</p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th>Requested</th><th>Format</th><th>Topic</th><th>Status</th><th>Engineer</th><th>Scheduled</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($consultations as $c): ?>
        <tr>
          <td class="help-text"><?= View::e($c['created_at']) ?></td>
          <td><?= $c['type'] === 'in_person' ? '🚗 In-person' : '💬 Chat/video' ?></td>
          <td><?= View::e($c['topic']) ?></td>
          <td><span class="badge badge-<?= $c['status']==='completed'?'green':($c['status']==='cancelled'?'red':($c['status']==='scheduled'?'blue':'yellow')) ?>"><?= View::e(ucfirst($c['status'])) ?></span></td>
          <td><?= View::e($c['assigned_engineer'] ?: '—') ?></td>
          <td class="help-text"><?= View::e($c['scheduled_at'] ?: ($c['preferred_date'] ?: '—')) ?></td>
          <td>
            <?php if (in_array($c['status'], ['requested', 'scheduled'], true)): ?>
              <form method="post" action="/app/consultations/<?= $c['id'] ?>/cancel" onsubmit="return confirm('Cancel this consultation request?');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-light">Cancel</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
