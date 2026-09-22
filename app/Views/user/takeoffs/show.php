<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <div>
    <h1><?= View::e($takeoff['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;" id="scale-status">
      <?= $takeoff['plan_image_path'] ? t('user.takeoffs.calibrating_scale') : t('user.takeoffs.no_plan_image') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;">
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/convert" onsubmit="return confirm('<?= t('user.takeoffs.convert_confirm') ?>');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-outline"><?= t('user.takeoffs.convert_to_estimate') ?></button>
    </form>
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/delete" onsubmit="return confirm('<?= t('user.takeoffs.delete_confirm') ?>');">
      <?= Csrf::field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
  <div class="card" style="padding:12px;overflow:auto;">
    <?php if ($takeoff['plan_image_path']): ?>
      <canvas id="takeoff-canvas" style="max-width:100%;border:1px solid var(--border);cursor:crosshair;"></canvas>
      <img id="plan-image" src="<?= View::e($takeoff['plan_image_path']) ?>" style="display:none;">
    <?php else: ?>
      <div class="empty-state"><div class="icon">🖼️</div><p><?= t('user.takeoffs.no_plan_uploaded') ?></p></div>
    <?php endif; ?>
  </div>

  <div>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.tools') ?></h3>
      <div class="toolbar" style="margin-bottom:8px;">
        <button type="button" class="btn btn-sm btn-outline" id="tool-calibrate"><?= t('user.takeoffs.calibrate_scale') ?></button>
      </div>
      <div class="toolbar">
        <button type="button" class="btn btn-sm btn-light" data-tool="length"><?= t('user.takeoffs.length_tool') ?></button>
        <button type="button" class="btn btn-sm btn-light" data-tool="area"><?= t('user.takeoffs.area_tool') ?></button>
        <button type="button" class="btn btn-sm btn-light" data-tool="count"><?= t('user.takeoffs.count_tool') ?></button>
      </div>
      <p class="help-text" id="tool-hint"><?= t('user.takeoffs.click_finish_hint') ?></p>
      <div class="toolbar">
        <button type="button" class="btn btn-sm btn-primary" id="btn-finish" style="display:none;"><?= t('user.takeoffs.finish') ?></button>
        <button type="button" class="btn btn-sm btn-light" id="btn-cancel" style="display:none;"><?= t('common.cancel') ?></button>
      </div>
    </div>

    <div class="card" id="pending-panel" style="display:none;margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.save_measurement') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.value_label') ?> <strong id="pending-value"></strong></p>
      <div class="form-group"><label><?= t('user.takeoffs.label_field') ?></label><input type="text" id="pending-label" placeholder="e.g. North wall"></div>
      <div class="form-group"><label><?= t('user.takeoffs.cost_per_unit') ?></label><input type="number" step="0.01" id="pending-cost" value="0"></div>
      <button type="button" class="btn btn-sm btn-outline" id="open-library-picker" style="margin-bottom:10px;"><?= t('user.takeoffs.pick_cost_library') ?></button>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-measurement"><?= t('common.save') ?></button>
    </div>

    <div id="calibrate-panel" style="display:none;margin-bottom:16px;" class="card">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.set_scale') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.set_scale_hint') ?></p>
      <div class="form-group"><label><?= t('user.takeoffs.real_length') ?></label><input type="number" step="0.01" id="calibrate-length" value="1"></div>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-calibration"><?= t('user.takeoffs.save_scale') ?></button>
    </div>

    <div class="card">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.measurements') ?></h3>
      <?php if (empty($measurements)): ?>
        <p class="help-text"><?= t('user.takeoffs.no_measurements_yet') ?></p>
      <?php else: ?>
        <div class="card-list">
          <?php foreach ($measurements as $m): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding:6px 0;">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= View::e($m['label']) ?></div>
                <div class="help-text"><bdi><?= View::e(number_format((float)$m['value'],2)) ?> <?= View::e($m['unit']) ?></bdi> · <?= View::money((float)$m['total_cost']) ?></div>
              </div>
              <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/measurements/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= t('user.takeoffs.remove_measurement_confirm') ?>');">
                <?= Csrf::field() ?>
                <button type="submit" class="btn btn-sm btn-light">✕</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="total-row" style="margin-top:12px;"><?= View::money($totalCost) ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require BASE_PATH . '/app/Views/user/partials/library-picker.php'; ?>

<script>
(function() {
  document.addEventListener('library-item-picked', (e) => {
    const labelEl = document.getElementById('pending-label');
    const costEl = document.getElementById('pending-cost');
    if (labelEl && !labelEl.value) labelEl.value = e.detail.description;
    if (costEl) costEl.value = e.detail.unitCost;
  });
})();
(function() {
  const csrfToken = <?= json_encode(\App\Core\Csrf::token()) ?>;
  const takeoffId = <?= (int) $takeoff['id'] ?>;
  let scalePxPerUnit = <?= (float) $takeoff['scale_px_per_unit'] ?>;
  const scaleUnit = <?= json_encode($takeoff['scale_unit']) ?>;
  const savedMeasurements = <?= json_encode(array_map(fn($m) => ['type'=>$m['type'],'points'=>json_decode($m['points_json'],true)], $measurements)) ?>;

  const canvas = document.getElementById('takeoff-canvas');
  const scaleStatus = document.getElementById('scale-status');
  scaleStatus.textContent = scalePxPerUnit > 1 ? ('Scale set: ' + scalePxPerUnit.toFixed(2) + ' px = 1 ' + scaleUnit) : 'Scale not set yet — use "Calibrate Scale" for accurate measurements.';

  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const img = document.getElementById('plan-image');

  let mode = null; // 'calibrate' | 'length' | 'area' | 'count'
  let points = [];
  let pendingValue = null;
  let pendingType = null;

  function draw() {
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    ctx.drawImage(img, 0, 0);

    savedMeasurements.forEach(m => drawShape(m.type, m.points, '#0f6e5f'));
    if (points.length) drawShape(mode, points, '#d4a017', true);
  }

  function drawShape(type, pts, color, live) {
    if (!pts || !pts.length) return;
    ctx.strokeStyle = color;
    ctx.fillStyle = color + '55';
    ctx.lineWidth = 2;

    if (type === 'count') {
      pts.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, 6, 0, Math.PI * 2);
        ctx.fill();
      });
      return;
    }

    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    pts.slice(1).forEach(p => ctx.lineTo(p.x, p.y));
    if (type === 'area' && !live) ctx.closePath();
    if (type === 'area') ctx.fill();
    ctx.stroke();

    pts.forEach(p => {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 3, 0, Math.PI * 2);
      ctx.fillStyle = color;
      ctx.fill();
    });
  }

  if (img.complete) draw(); else img.onload = draw;

  function canvasPoint(e) {
    const rect = canvas.getBoundingClientRect();
    const scaleX = canvas.width / rect.width;
    const scaleY = canvas.height / rect.height;
    return { x: (e.clientX - rect.left) * scaleX, y: (e.clientY - rect.top) * scaleY };
  }

  function dist(a, b) { return Math.hypot(b.x - a.x, b.y - a.y); }

  function polygonArea(pts) {
    let area = 0;
    for (let i = 0; i < pts.length; i++) {
      const j = (i + 1) % pts.length;
      area += pts[i].x * pts[j].y - pts[j].x * pts[i].y;
    }
    return Math.abs(area / 2);
  }

  canvas.addEventListener('click', (e) => {
    if (!mode) return;
    points.push(canvasPoint(e));
    draw();

    if (mode === 'calibrate' && points.length === 2) {
      document.getElementById('calibrate-panel').style.display = 'block';
    }
    if (mode === 'count') {
      // count mode: each click is immediately part of the running set; Finish saves the count.
    }
  });

  document.getElementById('tool-calibrate').addEventListener('click', () => startMode('calibrate'));
  document.querySelectorAll('[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => startMode(btn.dataset.tool));
  });

  function startMode(m) {
    mode = m;
    points = [];
    document.getElementById('calibrate-panel').style.display = 'none';
    document.getElementById('pending-panel').style.display = 'none';
    document.getElementById('btn-finish').style.display = m === 'calibrate' ? 'none' : 'inline-block';
    document.getElementById('btn-cancel').style.display = 'inline-block';
    const hints = {
      calibrate: 'Click the two ends of a known-length line on the plan.',
      length: 'Click along the path you want to measure, then press Finish.',
      area: 'Click each corner of the area, then press Finish.',
      count: 'Click each item to count, then press Finish.',
    };
    document.getElementById('tool-hint').textContent = hints[m] || '';
    draw();
  }

  document.getElementById('btn-cancel').addEventListener('click', () => {
    mode = null; points = [];
    document.getElementById('btn-finish').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'none';
    document.getElementById('calibrate-panel').style.display = 'none';
    document.getElementById('pending-panel').style.display = 'none';
    draw();
  });

  document.getElementById('btn-finish').addEventListener('click', () => {
    if (mode === 'length' && points.length >= 2) {
      let px = 0;
      for (let i = 1; i < points.length; i++) px += dist(points[i-1], points[i]);
      pendingValue = scalePxPerUnit > 0 ? px / scalePxPerUnit : px;
      pendingType = 'length';
      showPending(pendingValue.toFixed(2) + ' ' + scaleUnit);
    } else if (mode === 'area' && points.length >= 3) {
      const pxArea = polygonArea(points);
      pendingValue = scalePxPerUnit > 0 ? pxArea / (scalePxPerUnit * scalePxPerUnit) : pxArea;
      pendingType = 'area';
      showPending(pendingValue.toFixed(2) + ' ' + scaleUnit + '²');
    } else if (mode === 'count' && points.length >= 1) {
      pendingValue = points.length;
      pendingType = 'count';
      showPending(pendingValue + ' item(s)');
    }
  });

  function showPending(text) {
    document.getElementById('pending-value').textContent = text;
    document.getElementById('pending-panel').style.display = 'block';
  }

  document.getElementById('btn-save-measurement').addEventListener('click', () => {
    const label = document.getElementById('pending-label').value || pendingType;
    const cost = parseFloat(document.getElementById('pending-cost').value) || 0;

    fetch('/app/takeoffs/' + takeoffId + '/measurements', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: csrfToken, type: pendingType, label, value: pendingValue, unit_cost: cost, points: JSON.stringify(points) })
    }).then(r => r.json()).then(() => location.reload());
  });

  document.getElementById('btn-save-calibration').addEventListener('click', () => {
    const realLength = parseFloat(document.getElementById('calibrate-length').value) || 0;
    const px = dist(points[0], points[1]);
    if (realLength <= 0) return;

    fetch('/app/takeoffs/' + takeoffId + '/calibrate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _csrf: csrfToken, pixel_distance: px, real_length: realLength })
    }).then(r => r.json()).then(() => location.reload());
  });
})();
</script>
