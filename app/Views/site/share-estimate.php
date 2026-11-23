<?php use App\Core\View; use App\Core\Csrf; ?>
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">

    <?php if (!empty($_SESSION['flash'])): ?>
      <?php foreach ($_SESSION['flash'] as $type => $messages): foreach ($messages as $m): ?>
        <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>"><?= View::e($m) ?></div>
      <?php endforeach; endforeach; unset($_SESSION['flash']); endif; ?>

    <div class="card" style="padding:32px;">
      <div style="display:flex;justify-content:space-between;align-items:start;flex-wrap:wrap;gap:12px;">
        <div>
          <div class="eyebrow">Estimate from <?= View::e($company['name'] ?? '') ?></div>
          <h1 style="margin-top:4px;"><?= View::e($estimate['title']) ?></h1>
          <p class="help-text">Prepared for <?= View::e($client['name'] ?? 'you') ?> · <?= View::e($estimate['created_at']) ?></p>
        </div>
        <span class="badge badge-<?= $estimate['status']==='accepted'?'green':($estimate['status']==='declined'?'red':'yellow') ?>" style="font-size:13px;padding:6px 14px;"><?= View::e(ucfirst($estimate['status'])) ?></span>
      </div>

      <table class="data" style="margin-top:24px;">
        <thead><tr><th>Description</th><th>Qty</th><th>Unit cost</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr><td><?= View::e($it['description']) ?></td><td><?= View::e($it['qty']) ?></td><td><?= View::money((float)$it['unit_cost']) ?></td><td><?= View::money((float)$it['total']) ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="total-row" style="margin-top:10px;">Total: <?= View::money((float)$estimate['total']) ?></div>

      <a href="/e/<?= View::e($estimate['share_token']) ?>/pdf" target="_blank" class="btn btn-outline" style="margin-top:16px;">⬇ Download PDF</a>
    </div>

    <?php if ($estimate['status'] === 'accepted'): ?>
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">✅</div>
        <h3>Signed and accepted</h3>
        <p class="help-text">Signed by <strong><?= View::e($estimate['signed_by_name']) ?></strong> on <?= View::e($estimate['signed_at']) ?></p>
        <?php if (!empty($estimate['signature_data'])): ?>
          <img src="<?= View::e($estimate['signature_data']) ?>" alt="Signature" style="max-width:320px;border:1px solid var(--border);border-radius:8px;margin-top:10px;background:#fff;">
        <?php endif; ?>
      </div>
    <?php elseif ($estimate['status'] === 'declined'): ?>
      <div class="card" style="margin-top:20px;padding:32px;text-align:center;">
        <div style="font-size:36px;">❌</div>
        <h3>Declined</h3>
        <p class="help-text">This estimate was declined. Contact <?= View::e($company['name'] ?? 'the contractor') ?> if this was a mistake.</p>
      </div>
    <?php else: ?>
      <div class="card" style="margin-top:20px;padding:32px;">
        <h3>Review & sign</h3>
        <p class="help-text">By signing below you're approving this estimate as the basis for the project.</p>

        <form method="post" action="/e/<?= View::e($estimate['share_token']) ?>/sign" id="sign-form">
          <?= Csrf::field() ?>
          <input type="hidden" name="decision" id="decision-input" value="accept">
          <input type="hidden" name="signature_data" id="signature-data-input">

          <div class="form-group">
            <label>Your full name</label>
            <input type="text" name="signed_by_name" required placeholder="Type your name">
          </div>

          <div class="form-group">
            <label>Signature</label>
            <canvas id="sig-pad" width="600" height="180" style="border:1px solid var(--border);border-radius:8px;width:100%;max-width:600px;height:180px;touch-action:none;background:#fff;"></canvas>
            <div style="margin-top:6px;">
              <button type="button" id="sig-clear" class="btn btn-sm btn-light">Clear signature</button>
            </div>
          </div>

          <div style="display:flex;gap:10px;margin-top:16px;">
            <button type="submit" id="sign-accept" class="btn btn-primary">✅ Sign & Accept</button>
            <button type="submit" id="sign-decline" class="btn btn-light" formnovalidate>Decline</button>
          </div>
        </form>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if (!in_array($estimate['status'], ['accepted', 'declined'], true)): ?>
<script>
(function() {
  const canvas = document.getElementById('sig-pad');
  const ctx = canvas.getContext('2d');
  const ratio = canvas.width / canvas.getBoundingClientRect().width || 1;
  let drawing = false;
  let hasSignature = false;

  function pos(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
  }

  canvas.addEventListener('pointerdown', (e) => {
    drawing = true;
    hasSignature = true;
    const p = pos(e);
    ctx.beginPath();
    ctx.moveTo(p.x, p.y);
    canvas.setPointerCapture(e.pointerId);
  });
  canvas.addEventListener('pointermove', (e) => {
    if (!drawing) return;
    const p = pos(e);
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#0a4d42';
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
  });
  ['pointerup', 'pointerleave', 'pointercancel'].forEach(evt => canvas.addEventListener(evt, () => { drawing = false; }));

  document.getElementById('sig-clear').addEventListener('click', () => {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSignature = false;
  });

  const form = document.getElementById('sign-form');
  const decisionInput = document.getElementById('decision-input');
  const sigInput = document.getElementById('signature-data-input');

  document.getElementById('sign-accept').addEventListener('click', (e) => {
    if (!hasSignature) {
      e.preventDefault();
      alert('Please draw your signature before submitting.');
      return;
    }
    decisionInput.value = 'accept';
    sigInput.value = canvas.toDataURL('image/png');
  });
  document.getElementById('sign-decline').addEventListener('click', () => {
    decisionInput.value = 'decline';
  });
})();
</script>
<?php endif; ?>
