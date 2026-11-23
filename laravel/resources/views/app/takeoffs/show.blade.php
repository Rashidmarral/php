@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e($takeoff['name']) ?></h1>
    <p class="help-text" style="margin-top:4px;" id="scale-status">
      <?= $currentPage ? t('user.takeoffs.calibrating_scale') : t('user.takeoffs.no_plan_image') ?>
    </p>
  </div>
  <div style="display:flex;gap:8px;">
    <?php
      $canAiAnalyze = $aiConfigured && $currentPage && !$isViewingOldRevision;
      $aiTooltip = !$aiConfigured
        ? t('user.takeoffs.ai_not_configured')
        : (!$currentPage ? t('user.takeoffs.ai_needs_plan_image') : ($isViewingOldRevision ? t('user.takeoffs.revision_old_action_blocked') : ''));
    ?>
    <?php if (!$isViewingOldRevision): ?>
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/pages/<?= $currentPage['id'] ?? 0 ?>/ai-analyze" id="ai-analyze-form" title="<?= e($aiTooltip) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline" id="ai-analyze-btn" <?= $canAiAnalyze ? '' : 'disabled' ?> title="<?= e($aiTooltip) ?>">✨ <?= t('user.takeoffs.ai_analyze') ?></button>
    </form>
    <?php endif; ?>
    <?php if (count($pages) > 1): ?>
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/ai-analyze-all" id="ai-analyze-all-form" title="<?= e($aiConfigured ? '' : t('user.takeoffs.ai_not_configured')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline" id="ai-analyze-all-btn" <?= $aiConfigured ? '' : 'disabled' ?> title="<?= e($aiConfigured ? '' : t('user.takeoffs.ai_not_configured')) ?>">✨ <?= t('user.takeoffs.ai_analyze_all') ?></button>
    </form>
    <?php endif; ?>
    <?php if ($currentPage && !$isViewingOldRevision): ?>
    <details style="display:inline-block;">
      <summary class="btn btn-outline" style="cursor:pointer;display:inline-block;">📤 <?= t('user.takeoffs.upload_revised_plan') ?></summary>
      <div class="card" style="position:absolute;z-index:5;margin-top:8px;min-width:280px;">
        <p class="help-text" style="margin-top:0;"><?= t('user.takeoffs.upload_revised_plan_hint') ?></p>
        <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/pages/<?= $currentPage['id'] ?>/revise" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="form-group" style="margin:0 0 8px;">
            <label><?= t('common.file') ?></label>
            <input type="file" name="plan_image" required accept=".jpg,.jpeg,.png,.webp">
          </div>
          <button type="submit" class="btn btn-sm btn-primary"><?= t('common.upload') ?></button>
        </form>
      </div>
    </details>
    <?php endif; ?>
    <?php if ($canCompareRevision): ?>
    <button type="button" class="btn btn-outline" id="btn-compare-revision" <?= $aiConfigured ? '' : 'disabled' ?> title="<?= e($aiConfigured ? '' : t('user.takeoffs.ai_not_configured')) ?>">🔍 <?= t('user.takeoffs.compare_revision_button') ?></button>
    <?php endif; ?>
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/convert" onsubmit="return confirm('<?= t('user.takeoffs.convert_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-outline"><?= t('user.takeoffs.convert_to_estimate') ?></button>
    </form>
    <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/delete" onsubmit="return confirm('<?= t('user.takeoffs.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<?php if (count($versionChain) > 1): ?>
<div class="card" style="margin-bottom:16px;padding:10px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:6px;">
    <div>
      <h3 style="font-size:13px;margin:0;"><?= t('user.takeoffs.revision_history_heading') ?></h3>
      <p class="help-text" style="margin:4px 0 0;"><?= t('user.takeoffs.revision_kept_hint') ?></p>
    </div>
    <div style="display:flex;gap:6px;flex-wrap:wrap;">
      <?php foreach ($versionChain as $v): ?>
        <?php $isActive = $currentPage && (int) $v['id'] === (int) $currentPage['id']; ?>
        <a href="/app/takeoffs/<?= $takeoff['id'] ?>?page_id=<?= $v['id'] ?>"
           class="btn btn-sm <?= $isActive ? 'btn-primary' : 'btn-light' ?>">
          <?= e(t('user.takeoffs.revision_version_short', ['version' => $v['version']])) ?>
          <?php if (!$v['is_current']): ?>&nbsp;(<?= t('user.takeoffs.revision_superseded_badge') ?>)<?php endif; ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<?php if ($isViewingOldRevision && $currentPage): ?>
<div class="card" style="margin-bottom:16px;padding:12px;border:1px solid var(--border);background:#fff8e6;">
  <strong><?= e(t('user.takeoffs.revision_old_banner', ['version' => $currentPage['version']])) ?></strong>
  <?php if ($currentVersionPage): ?>
    &nbsp;<a href="/app/takeoffs/<?= $takeoff['id'] ?>?page_id=<?= $currentVersionPage['id'] ?>"><?= t('user.takeoffs.revision_view_current') ?></a>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (count($pages) > 1): ?>
<div class="card" style="margin-bottom:16px;padding:10px;">
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
    <h3 style="font-size:13px;margin:0;"><?= t('user.takeoffs.pages_heading') ?></h3>
    <span class="badge badge-gray"><?= t('user.takeoffs.page_count_badge', ['count' => count($pages)]) ?></span>
  </div>
  <div style="display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;">
    <?php foreach ($pages as $p): ?>
      <?php $active = $currentPage && (int) $p['id'] === (int) $currentPage['id']; ?>
      <a href="/app/takeoffs/<?= $takeoff['id'] ?>?page_id=<?= $p['id'] ?>"
         style="flex:0 0 auto;width:84px;text-align:center;text-decoration:none;border:2px solid <?= $active ? 'var(--brand)' : 'var(--border)' ?>;border-radius:8px;padding:4px;background:#fff;">
        <img src="<?= e($p['image_path']) ?>" alt="" style="width:100%;height:56px;object-fit:cover;border-radius:4px;display:block;">
        <span style="display:block;font-size:11px;margin-top:3px;color:<?= $active ? 'var(--brand)' : 'var(--muted)' ?>;font-weight:600;">
          <?= t('user.takeoffs.page_label', ['number' => $p['page_number']]) ?>
        </span>
      </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
  <div class="card" style="padding:12px;overflow:auto;">
    <?php if ($currentPage): ?>
      <canvas id="takeoff-canvas" style="max-width:100%;border:1px solid var(--border);cursor:crosshair;"></canvas>
      <img id="plan-image" src="<?= e($currentPage['image_path']) ?>" style="display:none;">
    <?php else: ?>
      <div class="empty-state"><div class="icon">🖼️</div><p><?= t('user.takeoffs.no_plan_uploaded') ?></p></div>
    <?php endif; ?>
  </div>

  <div>
    <?php if ($isViewingOldRevision): ?>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.revision_readonly_heading') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.revision_readonly_hint') ?></p>
    </div>
    <?php else: ?>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.tools') ?></h3>
      <div class="toolbar" style="margin-bottom:8px;">
        <button type="button" class="btn btn-sm btn-outline" id="tool-calibrate"><?= t('user.takeoffs.calibrate_scale') ?></button>
      </div>
      <?php
        $areaParentCandidates = array_values(array_filter($measurements, fn ($m) => $m['type'] === 'area' && !$m['is_cutout']));
        $hasAreaParentCandidates = count($areaParentCandidates) > 0;
      ?>
      <div class="toolbar">
        <button type="button" class="btn btn-sm btn-light" data-tool="length"><?= t('user.takeoffs.length_tool') ?></button>
        <button type="button" class="btn btn-sm btn-light" data-tool="area"><?= t('user.takeoffs.area_tool') ?></button>
        <button type="button" class="btn btn-sm btn-light" data-tool="count"><?= t('user.takeoffs.count_tool') ?></button>
        <?php if ($hasAreaParentCandidates): ?>
          <button type="button" class="btn btn-sm btn-light" data-tool="cutout" id="tool-cutout"><?= t('user.takeoffs.cutout_tool') ?></button>
        <?php else: ?>
          <button type="button" class="btn btn-sm btn-light" disabled title="<?= e(t('user.takeoffs.cutout_needs_area_hint')) ?>"><?= t('user.takeoffs.cutout_tool') ?></button>
        <?php endif; ?>
        <?php
          $autocountTooltip = $aiConfigured ? '' : t('user.takeoffs.ai_not_configured');
        ?>
        <button type="button" class="btn btn-sm btn-light" data-tool="autocount" id="tool-autocount" <?= $aiConfigured ? '' : 'disabled' ?> title="<?= e($autocountTooltip) ?>"><?= t('user.takeoffs.autocount_tool') ?></button>
        <?php if (count($pages) > 1): ?>
        <!-- "Search all pages" is a checkbox next to the Autocount tool, not a second tool
             button — mirrors the exact same box-drawing step (draw one reference box, same
             2-click interaction, same clampedAutocountBox()/cropAutocountReference()
             pipeline), it only changes what happens once that box is confirmed: a single-page
             search (unchecked, today's exact existing behavior) or a cross-sheet search
             (checked). See btn-run-autocount's own click handler below for where this is read. -->
        <label style="display:inline-flex;align-items:center;gap:4px;font-size:12px;margin-left:4px;" title="<?= e($autocountTooltip) ?>">
          <input type="checkbox" id="autocount-all-pages-toggle" <?= $aiConfigured ? '' : 'disabled' ?>>
          <?= t('user.takeoffs.autocount_all_pages_checkbox_label') ?>
        </label>
        <?php endif; ?>
      </div>
      <p class="help-text" id="tool-hint"><?= t('user.takeoffs.click_finish_hint') ?></p>
      <div class="toolbar">
        <button type="button" class="btn btn-sm btn-primary" id="btn-finish" style="display:none;"><?= t('user.takeoffs.finish') ?></button>
        <button type="button" class="btn btn-sm btn-light" id="btn-cancel" style="display:none;"><?= t('common.cancel') ?></button>
      </div>
    </div>

    <div class="card" id="cutout-parent-panel" style="display:none;margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.cutout_pick_parent') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.cutout_pick_parent_hint') ?></p>
      <div class="form-group">
        <label><?= t('user.takeoffs.cutout_parent_label') ?></label>
        <select id="cutout-parent-select">
          <?php foreach ($areaParentCandidates as $m): ?>
            <option value="<?= (int) $m['id'] ?>"><?= e($m['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="button" class="btn btn-primary btn-sm" id="btn-start-cutout"><?= t('user.takeoffs.cutout_start_drawing') ?></button>
    </div>

    <div class="card" id="pending-panel" style="display:none;margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.save_measurement') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.value_label') ?> <strong id="pending-value"></strong></p>
      <div class="form-group"><label><?= t('user.takeoffs.label_field') ?></label><input type="text" id="pending-label" placeholder="e.g. North wall"></div>
      <?php if (!empty($assemblies)): ?>
      <div class="form-group" id="pending-assembly-group">
        <label><?= t('user.takeoffs.assembly_label') ?></label>
        <select id="pending-assembly">
          <option value=""><?= t('user.takeoffs.assembly_none_option') ?></option>
          <?php foreach ($assemblies as $a): ?>
            <option value="<?= (int) $a['id'] ?>" data-unit-cost="<?= e($a['computed_unit_cost']) ?>" data-base-uom="<?= e($a['base_uom']) ?>">
              <?= e(local($a, 'name')) ?> — <?= e($a['base_uom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="help-text" id="pending-assembly-hint" style="display:none;"><?= t('user.takeoffs.assembly_hint') ?></p>
      </div>
      <?php endif; ?>
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

    <div id="autocount-panel" style="display:none;margin-bottom:16px;" class="card">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.autocount_panel_heading') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.autocount_panel_hint') ?></p>
      <button type="button" class="btn btn-primary btn-sm" id="btn-run-autocount"><?= t('user.takeoffs.autocount_find_matches') ?></button>
    </div>

    <div id="autocount-review-panel" style="display:none;margin-bottom:16px;" class="card">
      <h3 style="font-size:14px;" id="autocount-review-heading"><?= t('user.takeoffs.autocount_review_heading') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.autocount_review_hint') ?></p>
      <div id="autocount-list" style="max-height:220px;overflow-y:auto;margin-bottom:10px;"></div>
      <p class="help-text"><?= t('user.takeoffs.autocount_confirmed_count_label') ?> <strong id="autocount-confirmed-count">0</strong></p>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-autocount"><?= t('user.takeoffs.autocount_save') ?></button>
    </div>

    <?php if (count($pages) > 1): ?>
    <!--
      Cross-sheet ("Search all pages") review. The CURRENT page's own results reuse the
      canvas-marker-based review above (autocount-review-panel/autocount-list/
      drawAutocountMarkers()) completely unchanged — see renderAutocountReview() in the script
      below. Every OTHER page's results render here instead: a collapsible <details> per page
      with a plain Include all / Exclude all pair rather than a per-match toggle list, since a
      match drawn on a page that isn't on screen can't be visually cross-checked against the
      plan anyway — bulk include/exclude plus the match count is the honest amount of control
      to offer for a page you aren't looking at, and keeps this list usable on a 10+ page set.
      A page the search genuinely failed on shows that plainly rather than a silent "0".
    -->
    <div id="autocount-other-pages-panel" style="display:none;margin-bottom:16px;" class="card">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.autocount_all_pages_review_heading') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.autocount_all_pages_review_hint') ?></p>
      <p class="help-text" style="font-weight:600;"><?= t('user.takeoffs.autocount_other_pages_heading') ?></p>
      <div id="autocount-other-pages-list"></div>
      <button type="button" class="btn btn-primary btn-sm" id="btn-save-autocount-all-pages" style="margin-top:10px;"><?= t('user.takeoffs.autocount_save_all_pages') ?></button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($canCompareRevision): ?>
    <div class="card" id="compare-findings-panel" style="display:none;margin-bottom:16px;">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.compare_findings_heading') ?></h3>
      <p class="help-text"><?= t('user.takeoffs.compare_findings_hint') ?></p>
      <ul id="compare-findings-list" style="margin:0;padding-left:18px;font-size:13px;"></ul>
    </div>
    <?php endif; ?>

    <div class="card">
      <h3 style="font-size:14px;"><?= t('user.takeoffs.measurements') ?></h3>
      <?php if (empty($measurements)): ?>
        <p class="help-text"><?= t('user.takeoffs.no_measurements_yet') ?></p>
      <?php else: ?>
        <div class="card-list">
          <?php foreach ($measurements as $m): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);padding:6px 0;<?= $m['is_cutout'] ? 'padding-left:18px;' : '' ?>">
              <div>
                <div style="font-size:13px;font-weight:600;"><?= $m['is_cutout'] ? '↳ ' : '' ?><?= e($m['label']) ?></div>
                <div class="help-text">
                  <bdi><?= e(number_format((float)$m['value'],2)) ?> <?= e($m['unit']) ?></bdi>
                  <?php if (!$m['is_cutout'] && $m['gross_value'] !== null): ?>
                    &nbsp;<?= e(t('user.takeoffs.cutout_net_note', [
                      'gross' => number_format((float) $m['gross_value'], 2),
                      'cut' => number_format((float) $m['cutout_total'], 2),
                      'unit' => $m['unit'],
                    ])) ?>
                  <?php endif; ?>
                  · <?= money((float)$m['total_cost']) ?>
                </div>
                <?php if ($m['is_cutout'] && $m['parent_label']): ?>
                  <div class="help-text" style="font-style:italic;"><?= e(t('user.takeoffs.cutout_from_label', ['label' => $m['parent_label']])) ?></div>
                <?php endif; ?>
              </div>
              <?php if (!$isViewingOldRevision): ?>
              <form method="post" action="/app/takeoffs/<?= $takeoff['id'] ?>/measurements/<?= $m['id'] ?>/delete" onsubmit="return confirm('<?= t('user.takeoffs.remove_measurement_confirm') ?>');">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-light">✕</button>
              </form>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="total-row" style="margin-top:12px;"><?= money($totalCost) ?></div>
      <?php endif; ?>
      <?php if (count($pages) > 1): ?>
        <p class="help-text" style="margin-top:10px;"><?= t('user.takeoffs.grand_total_label', ['count' => count($pages)]) ?> <strong><?= money($grandTotalCost) ?></strong></p>
      <?php endif; ?>
    </div>
  </div>
</div>

@include('app.partials.library-picker')

<script>
(function() {
  const aiForm = document.getElementById('ai-analyze-form');
  if (aiForm) {
    aiForm.addEventListener('submit', (e) => {
      if (!confirm(<?= json_encode(t('user.takeoffs.ai_analyze_confirm')) ?>)) {
        e.preventDefault();
        return;
      }
      const btn = document.getElementById('ai-analyze-btn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = <?= json_encode(t('user.takeoffs.ai_analyzing')) ?>;
      }
    });
  }
  const aiAllForm = document.getElementById('ai-analyze-all-form');
  if (aiAllForm) {
    aiAllForm.addEventListener('submit', (e) => {
      if (!confirm(<?= json_encode(t('user.takeoffs.ai_analyze_all_confirm')) ?>)) {
        e.preventDefault();
        return;
      }
      const btn = document.getElementById('ai-analyze-all-btn');
      if (btn) {
        btn.disabled = true;
        btn.textContent = <?= json_encode(t('user.takeoffs.ai_analyzing')) ?>;
      }
    });
  }
})();
<?php if ($canCompareRevision): ?>
(function() {
  const compareBtn = document.getElementById('btn-compare-revision');
  if (!compareBtn) return;

  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const takeoffId = <?= (int) $takeoff['id'] ?>;
  const pageId = <?= (int) $currentPage['id'] ?>;
  const panel = document.getElementById('compare-findings-panel');
  const list = document.getElementById('compare-findings-list');

  compareBtn.addEventListener('click', () => {
    if (!confirm(<?= json_encode(t('user.takeoffs.compare_revision_confirm')) ?>)) {
      return;
    }
    compareBtn.disabled = true;
    const originalText = compareBtn.textContent;
    compareBtn.textContent = <?= json_encode(t('user.takeoffs.compare_revision_comparing')) ?>;

    fetch('/app/takeoffs/' + takeoffId + '/pages/' + pageId + '/compare-revision', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _token: csrfToken })
    }).then(r => r.json()).then(data => {
      compareBtn.disabled = false;
      compareBtn.textContent = originalText;
      if (!data.ok) {
        alert(data.error || <?= json_encode(t('user.takeoffs.compare_revision_failed')) ?>);
        return;
      }
      if (list) {
        list.innerHTML = '';
        (data.findings && data.findings.length ? data.findings : [<?= json_encode(t('user.takeoffs.compare_no_differences')) ?>]).forEach(f => {
          const li = document.createElement('li');
          li.textContent = f;
          list.appendChild(li);
        });
      }
      if (panel) panel.style.display = 'block';
    }).catch(() => {
      compareBtn.disabled = false;
      compareBtn.textContent = originalText;
      alert(<?= json_encode(t('user.takeoffs.compare_revision_failed')) ?>);
    });
  });
})();
<?php endif; ?>
(function() {
  document.addEventListener('library-item-picked', (e) => {
    const labelEl = document.getElementById('pending-label');
    const costEl = document.getElementById('pending-cost');
    if (labelEl && !labelEl.value) labelEl.value = e.detail.description;
    if (costEl) costEl.value = e.detail.unitCost;
  });
})();
(function() {
  const csrfToken = <?= json_encode(csrf_token()) ?>;
  const takeoffId = <?= (int) $takeoff['id'] ?>;
  const pageId = <?= $currentPage ? (int) $currentPage['id'] : 'null' ?>;
  // Viewing a superseded (old) revision is historical reference only — the plan image and
  // its own saved measurements are still drawn below, but every measuring-tool button this
  // script would otherwise wire up (Calibrate, Length/Area/Count/Cutout/Autocount, Finish,
  // etc.) isn't rendered into the DOM at all on an old revision (see show() /
  // $isViewingOldRevision), so none of that wiring runs here either.
  const isReadOnly = <?= $isViewingOldRevision ? 'true' : 'false' ?>;
  let scalePxPerUnit = <?= $currentPage ? (float) $currentPage['scale_px_per_unit'] : 1 ?>;
  const scaleUnit = <?= json_encode($currentPage['scale_unit'] ?? 'm') ?>;
  const savedMeasurements = <?= json_encode(array_map(fn($m) => [
    'id' => $m['id'],
    'type' => $m['type'],
    'points' => json_decode($m['points_json'], true),
    'isCutout' => (bool) $m['is_cutout'],
    'label' => $m['label'],
  ], $measurements)) ?>;
  const cutoutSuffix = <?= json_encode(' ' . t('user.takeoffs.cutout_suffix_short')) ?>;

  const canvas = document.getElementById('takeoff-canvas');
  const scaleStatus = document.getElementById('scale-status');
  scaleStatus.textContent = scalePxPerUnit > 1 ? ('Scale set: ' + scalePxPerUnit.toFixed(2) + ' px = 1 ' + scaleUnit) : 'Scale not set yet — use "Calibrate Scale" for accurate measurements.';

  if (!canvas || !pageId) return;
  const ctx = canvas.getContext('2d');
  const img = document.getElementById('plan-image');

  let mode = null; // 'calibrate' | 'length' | 'area' | 'count' | 'cutout' | 'autocount'
  let points = [];
  let pendingValue = null;
  let pendingType = null;
  let pendingIsCutout = false;
  let cutoutParentId = null;
  // Autocount's own candidate matches — never a saved measurement on its own; see
  // renderAutocountReview()/btn-save-autocount below for how a reviewed, confirmed subset
  // becomes one ordinary 'count' TakeoffMeasurement.
  let autocountDetections = [];
  // "Search all pages" state — autocountDetections above still holds THIS page's own
  // candidates (so the existing canvas markers/toggle list keep working unchanged);
  // otherPagesResults holds every OTHER current page's own candidates + failed flag, each
  // with its own confirmed flag per point for the include-all/exclude-all list below.
  // autocountAllPagesMode is read by renderAutocountReview() to hide its own single-page
  // Save button in favor of the one combined "Save all confirmed matches" button.
  let autocountAllPagesMode = false;
  let otherPagesResults = [];

  function draw() {
    canvas.width = img.naturalWidth;
    canvas.height = img.naturalHeight;
    ctx.drawImage(img, 0, 0);

    savedMeasurements.forEach(m => drawShape(m.type, m.points, '#0f6e5f', false, m.isCutout));
    // While drawing, a cutout is still geometrically an 'area' (polygon-click-to-draw, same
    // points/polygonArea() code as the Area tool) — only the save request and the rendering
    // style differ.
    if (points.length) drawShape(mode === 'cutout' ? 'area' : mode, points, '#d4a017', true, mode === 'cutout');
    drawAutocountMarkers();
  }

  // Autocount's detected points get their own small square marker — deliberately distinct
  // from the Count tool's solid circle — so a reviewed-but-unsaved detection never looks like
  // an already-saved measurement. A confirmed marker is filled solid; an excluded one is
  // drawn faint/hollow, in sync with the toggle list in renderAutocountReview() below.
  function drawAutocountMarkers() {
    if (!autocountDetections.length) return;
    autocountDetections.forEach(d => {
      ctx.lineWidth = 2;
      ctx.strokeStyle = '#7b2cbf';
      ctx.fillStyle = d.confirmed ? '#7b2cbf' : 'rgba(123,44,191,0.2)';
      ctx.beginPath();
      ctx.rect(d.x - 7, d.y - 7, 14, 14);
      ctx.fill();
      ctx.stroke();
    });
  }

  function drawShape(type, pts, color, live, isCutout) {
    if (!pts || !pts.length) return;
    ctx.lineWidth = 2;

    if (type === 'count') {
      ctx.strokeStyle = color;
      ctx.fillStyle = color;
      pts.forEach(p => {
        ctx.beginPath();
        ctx.arc(p.x, p.y, 6, 0, Math.PI * 2);
        ctx.fill();
      });
      return;
    }

    if (type === 'autocount') {
      // The reference box — a plain rectangle between the two clicked corners (in whichever
      // order they were clicked), not a polyline/polygon like the other tools.
      ctx.save();
      ctx.setLineDash([5, 4]);
      ctx.strokeStyle = color;
      if (pts.length >= 2) {
        const x = Math.min(pts[0].x, pts[1].x);
        const y = Math.min(pts[0].y, pts[1].y);
        ctx.strokeRect(x, y, Math.abs(pts[1].x - pts[0].x), Math.abs(pts[1].y - pts[0].y));
      } else {
        ctx.beginPath();
        ctx.arc(pts[0].x, pts[0].y, 4, 0, Math.PI * 2);
        ctx.stroke();
      }
      ctx.restore();
      return;
    }

    ctx.beginPath();
    ctx.moveTo(pts[0].x, pts[0].y);
    pts.slice(1).forEach(p => ctx.lineTo(p.x, p.y));
    if (type === 'area' && !live) ctx.closePath();

    if (type === 'area' && isCutout) {
      // Draw a cutout as a visually distinct "hole": a dashed red outline over a light,
      // near-transparent fill, so it reads differently from a regular (solid green) area
      // measurement at a glance, without changing how regular areas are rendered.
      ctx.save();
      ctx.setLineDash([6, 4]);
      ctx.strokeStyle = '#c0392b';
      ctx.fillStyle = 'rgba(255,255,255,0.65)';
      ctx.fill();
      ctx.stroke();
      ctx.restore();
    } else {
      ctx.strokeStyle = color;
      ctx.fillStyle = color + '55';
      if (type === 'area') ctx.fill();
      ctx.stroke();
    }

    ctx.fillStyle = isCutout ? '#c0392b' : color;
    pts.forEach(p => {
      ctx.beginPath();
      ctx.arc(p.x, p.y, 3, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  if (img.complete) draw(); else img.onload = draw;

  // Nothing below this line applies to a read-only old revision — every element the
  // following code binds to was deliberately left out of the DOM for one (see isReadOnly
  // above), so wiring up listeners for them would throw on a null lookup.
  if (isReadOnly) return;

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
    // Same 2-click-then-stop interaction as calibrate above — the second click defines the
    // reference box's opposite corner and immediately surfaces the "find matches" panel.
    if (mode === 'autocount' && points.length === 2) {
      document.getElementById('autocount-panel').style.display = 'block';
      const runBtn = document.getElementById('btn-run-autocount');
      const allPagesToggle = document.getElementById('autocount-all-pages-toggle');
      if (runBtn) {
        runBtn.textContent = (allPagesToggle && allPagesToggle.checked)
          ? <?= json_encode(t('user.takeoffs.autocount_find_matches_all_pages')) ?>
          : <?= json_encode(t('user.takeoffs.autocount_find_matches')) ?>;
      }
    }
    if (mode === 'count') {
      // count mode: each click is immediately part of the running set; Finish saves the count.
    }
  });

  document.getElementById('tool-calibrate').addEventListener('click', () => startMode('calibrate'));
  document.querySelectorAll('[data-tool]').forEach(btn => {
    btn.addEventListener('click', () => {
      // The Cutout tool needs to know which area it's cutting from before any drawing
      // starts — mirrors startMode()'s own hint-text panel pattern, just one step earlier.
      if (btn.dataset.tool === 'cutout') {
        showCutoutParentPicker();
      } else {
        startMode(btn.dataset.tool);
      }
    });
  });

  function showCutoutParentPicker() {
    mode = null;
    points = [];
    cutoutParentId = null;
    document.getElementById('calibrate-panel').style.display = 'none';
    document.getElementById('pending-panel').style.display = 'none';
    document.getElementById('btn-finish').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'inline-block';
    document.getElementById('cutout-parent-panel').style.display = 'block';
    document.getElementById('tool-hint').textContent = <?= json_encode(t('user.takeoffs.cutout_pick_parent_hint')) ?>;
    draw();
  }

  document.getElementById('btn-start-cutout').addEventListener('click', () => {
    const select = document.getElementById('cutout-parent-select');
    if (!select || !select.value) return;
    cutoutParentId = parseInt(select.value, 10);
    document.getElementById('cutout-parent-panel').style.display = 'none';
    startMode('cutout');
  });

  // The "other pages" panel only exists in the DOM when count($pages) > 1 (see the blade
  // template above) — guarded here so every reset path below can call this unconditionally.
  function resetAutocountAllPagesState() {
    autocountAllPagesMode = false;
    otherPagesResults = [];
    const panel = document.getElementById('autocount-other-pages-panel');
    if (panel) panel.style.display = 'none';
    const list = document.getElementById('autocount-other-pages-list');
    if (list) list.innerHTML = '';
  }

  function startMode(m) {
    mode = m;
    points = [];
    autocountDetections = [];
    resetAutocountAllPagesState();
    document.getElementById('calibrate-panel').style.display = 'none';
    document.getElementById('pending-panel').style.display = 'none';
    document.getElementById('cutout-parent-panel').style.display = 'none';
    document.getElementById('autocount-panel').style.display = 'none';
    document.getElementById('autocount-review-panel').style.display = 'none';
    // Autocount needs no Finish button — like calibrate, its second click is the trigger
    // (see the canvas click listener above), surfacing autocount-panel's own "Find matching
    // symbols" button instead.
    document.getElementById('btn-finish').style.display = (m === 'calibrate' || m === 'autocount') ? 'none' : 'inline-block';
    document.getElementById('btn-cancel').style.display = 'inline-block';
    resetAssemblyPicker();
    const hints = {
      calibrate: 'Click the two ends of a known-length line on the plan.',
      length: 'Click along the path you want to measure, then press Finish.',
      area: 'Click each corner of the area, then press Finish.',
      count: 'Click each item to count, then press Finish.',
      cutout: <?= json_encode(t('user.takeoffs.cutout_draw_hint')) ?>,
      autocount: <?= json_encode(t('user.takeoffs.autocount_draw_hint')) ?>,
    };
    document.getElementById('tool-hint').textContent = hints[m] || '';
    draw();
  }

  document.getElementById('btn-cancel').addEventListener('click', () => {
    mode = null; points = []; cutoutParentId = null; autocountDetections = [];
    resetAutocountAllPagesState();
    document.getElementById('btn-finish').style.display = 'none';
    document.getElementById('btn-cancel').style.display = 'none';
    document.getElementById('calibrate-panel').style.display = 'none';
    document.getElementById('pending-panel').style.display = 'none';
    document.getElementById('cutout-parent-panel').style.display = 'none';
    document.getElementById('autocount-panel').style.display = 'none';
    document.getElementById('autocount-review-panel').style.display = 'none';
    resetAssemblyPicker();
    draw();
  });

  // Clears the assembly dropdown back to "no assembly" and gives the cost field back to
  // free typing — called whenever the pending panel starts fresh for a new measurement
  // (a stale pick from a previous measurement, possibly of a different type, must never
  // carry over).
  function resetAssemblyPicker() {
    const select = document.getElementById('pending-assembly');
    const costEl = document.getElementById('pending-cost');
    const hint = document.getElementById('pending-assembly-hint');
    if (select) select.value = '';
    if (costEl) costEl.disabled = false;
    if (hint) hint.style.display = 'none';
  }

  const pendingAssemblySelect = document.getElementById('pending-assembly');
  if (pendingAssemblySelect) {
    pendingAssemblySelect.addEventListener('change', () => {
      const costEl = document.getElementById('pending-cost');
      const hint = document.getElementById('pending-assembly-hint');
      const opt = pendingAssemblySelect.selectedOptions[0];
      if (pendingAssemblySelect.value && opt) {
        // Auto-fill from the assembly's own items (qty_per_unit * unit_cost, summed —
        // computed server-side in TakeoffController::assembliesForPicker()) and lock the
        // field so it can't drift from what will actually be charged; picking "— No
        // assembly —" again hands typing back, exactly like before an assembly existed.
        if (costEl) {
          costEl.value = opt.dataset.unitCost;
          costEl.disabled = true;
        }
        if (hint) hint.style.display = 'block';
      } else {
        if (costEl) costEl.disabled = false;
        if (hint) hint.style.display = 'none';
      }
    });
  }

  document.getElementById('btn-finish').addEventListener('click', () => {
    if (mode === 'length' && points.length >= 2) {
      let px = 0;
      for (let i = 1; i < points.length; i++) px += dist(points[i-1], points[i]);
      pendingValue = scalePxPerUnit > 0 ? px / scalePxPerUnit : px;
      pendingType = 'length';
      pendingIsCutout = false;
      showPending(pendingValue.toFixed(2) + ' ' + scaleUnit);
    } else if ((mode === 'area' || mode === 'cutout') && points.length >= 3) {
      // Same polygon-click-to-draw interaction and polygonArea() math as the Area tool —
      // a cutout only differs in what gets carried through to the save request below.
      const pxArea = polygonArea(points);
      pendingValue = scalePxPerUnit > 0 ? pxArea / (scalePxPerUnit * scalePxPerUnit) : pxArea;
      pendingType = 'area';
      pendingIsCutout = (mode === 'cutout');
      let text = pendingValue.toFixed(2) + ' ' + scaleUnit + '²';
      if (pendingIsCutout) {
        text += cutoutSuffix;
      }
      showPending(text);
    } else if (mode === 'count' && points.length >= 1) {
      pendingValue = points.length;
      pendingType = 'count';
      pendingIsCutout = false;
      showPending(pendingValue + ' item(s)');
    }
  });

  function showPending(text) {
    document.getElementById('pending-value').textContent = text;
    document.getElementById('pending-panel').style.display = 'block';

    // An assembly only applies to a non-cutout length/area measurement (see
    // addMeasurement()'s server-side rejection of the same combinations) — a count has no
    // sensible "per-unit" to scale, and a cutout is never its own line item.
    const assemblyGroup = document.getElementById('pending-assembly-group');
    if (assemblyGroup) {
      const assemblyApplies = (pendingType === 'length' || pendingType === 'area') && !pendingIsCutout;
      assemblyGroup.style.display = assemblyApplies ? 'block' : 'none';
      if (!assemblyApplies) resetAssemblyPicker();
    }
  }

  document.getElementById('btn-save-measurement').addEventListener('click', () => {
    const label = document.getElementById('pending-label').value || pendingType;
    const cost = parseFloat(document.getElementById('pending-cost').value) || 0;
    const assemblySelect = document.getElementById('pending-assembly');
    const assemblyId = (assemblySelect && assemblySelect.value) ? assemblySelect.value : '';

    fetch('/app/takeoffs/' + takeoffId + '/pages/' + pageId + '/measurements', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        _token: csrfToken,
        type: pendingType,
        label,
        value: pendingValue,
        unit_cost: cost,
        points: JSON.stringify(points),
        is_cutout: pendingIsCutout ? '1' : '0',
        parent_measurement_id: (pendingIsCutout && cutoutParentId) ? cutoutParentId : '',
        assembly_id: assemblyId,
      })
    }).then(r => r.json()).then(() => location.reload());
  });

  document.getElementById('btn-save-calibration').addEventListener('click', () => {
    const realLength = parseFloat(document.getElementById('calibrate-length').value) || 0;
    const px = dist(points[0], points[1]);
    if (realLength <= 0) return;

    fetch('/app/takeoffs/' + takeoffId + '/pages/' + pageId + '/calibrate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _token: csrfToken, pixel_distance: px, real_length: realLength })
    }).then(r => r.json()).then(() => location.reload());
  });

  // Renders autocountDetections as a toggle list in the review panel — the "Found / Missed /
  // Wrong-Shape"-style human review STACK's own Autocount requires before anything is
  // treated as real, just expressed as an include/exclude toggle per candidate here. Always
  // rebuilt from the array rather than patched in place, so it can never drift out of sync
  // with what draw() renders on the canvas.
  function renderAutocountReview() {
    const list = document.getElementById('autocount-list');
    const countLabel = document.getElementById('autocount-confirmed-count');
    if (!list || !countLabel) return;

    list.innerHTML = '';
    if (autocountDetections.length === 0) {
      const empty = document.createElement('p');
      empty.className = 'help-text';
      empty.textContent = <?= json_encode(t('user.takeoffs.autocount_no_matches')) ?>;
      list.appendChild(empty);
    } else {
      autocountDetections.forEach((d, i) => {
        const row = document.createElement('div');
        row.style.display = 'flex';
        row.style.justifyContent = 'space-between';
        row.style.alignItems = 'center';
        row.style.padding = '4px 0';
        row.style.borderBottom = '1px solid var(--border)';
        row.style.opacity = d.confirmed ? '1' : '0.5';

        const label = document.createElement('span');
        label.style.fontSize = '12px';
        label.textContent = <?= json_encode(t('user.takeoffs.autocount_match_label')) ?> + ' #' + (i + 1)
          + (d.confirmed ? '' : ' — ' + <?= json_encode(t('user.takeoffs.autocount_excluded')) ?>);

        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'btn btn-sm btn-light';
        toggleBtn.textContent = d.confirmed
          ? <?= json_encode(t('user.takeoffs.autocount_exclude')) ?>
          : <?= json_encode(t('user.takeoffs.autocount_include')) ?>;
        toggleBtn.addEventListener('click', () => {
          d.confirmed = !d.confirmed;
          renderAutocountReview();
          draw();
        });

        row.appendChild(label);
        row.appendChild(toggleBtn);
        list.appendChild(row);
      });
    }

    countLabel.textContent = autocountDetections.filter(d => d.confirmed).length;
    document.getElementById('autocount-review-panel').style.display = 'block';

    // In "search all pages" mode, this list/canvas-marker review (unchanged above) still
    // covers only THIS page's own candidates — saving happens once, for every page together,
    // via the one combined "Save all confirmed matches" button below instead of this panel's
    // own single-page Save button.
    const heading = document.getElementById('autocount-review-heading');
    const saveBtn = document.getElementById('btn-save-autocount');
    if (heading) {
      heading.textContent = autocountAllPagesMode
        ? <?= json_encode(t('user.takeoffs.autocount_current_page_heading')) ?>.replace(':count', autocountDetections.filter(d => d.confirmed).length)
        : <?= json_encode(t('user.takeoffs.autocount_review_heading')) ?>;
    }
    if (saveBtn) saveBtn.style.display = autocountAllPagesMode ? 'none' : 'inline-block';
  }

  // Renders otherPagesResults (every OTHER current page's own candidates) as one collapsible
  // section per page — Include all / Exclude all rather than a per-match toggle (see the
  // blade template's own comment above autocount-other-pages-panel for why), plus a plain
  // "search failed" note for a page whose AiTakeoffAnalyzer call itself returned null.
  function renderOtherPagesReview() {
    const list = document.getElementById('autocount-other-pages-list');
    if (!list) return;
    list.innerHTML = '';

    otherPagesResults.forEach(p => {
      const details = document.createElement('details');
      details.style.borderBottom = '1px solid var(--border)';
      details.style.padding = '4px 0';

      const summary = document.createElement('summary');
      summary.style.cursor = 'pointer';
      summary.style.fontSize = '13px';
      if (p.failed) {
        summary.textContent = <?= json_encode(t('user.takeoffs.page_label')) ?>.replace(':number', p.page_number)
          + ' — ' + <?= json_encode(t('user.takeoffs.autocount_other_page_failed')) ?>;
      } else {
        summary.textContent = <?= json_encode(t('user.takeoffs.autocount_other_page_heading')) ?>
          .replace(':number', p.page_number)
          .replace(':count', p.points.filter(d => d.confirmed).length);
      }
      details.appendChild(summary);

      if (!p.failed && p.points.length > 0) {
        const body = document.createElement('div');
        body.style.margin = '6px 0 0 16px';

        const includeAllBtn = document.createElement('button');
        includeAllBtn.type = 'button';
        includeAllBtn.className = 'btn btn-sm btn-light';
        includeAllBtn.style.marginRight = '6px';
        includeAllBtn.textContent = <?= json_encode(t('user.takeoffs.autocount_include_all')) ?>;
        includeAllBtn.addEventListener('click', () => {
          p.points.forEach(d => d.confirmed = true);
          renderOtherPagesReview();
        });

        const excludeAllBtn = document.createElement('button');
        excludeAllBtn.type = 'button';
        excludeAllBtn.className = 'btn btn-sm btn-light';
        excludeAllBtn.textContent = <?= json_encode(t('user.takeoffs.autocount_exclude_all')) ?>;
        excludeAllBtn.addEventListener('click', () => {
          p.points.forEach(d => d.confirmed = false);
          renderOtherPagesReview();
        });

        body.appendChild(includeAllBtn);
        body.appendChild(excludeAllBtn);
        details.appendChild(body);
      } else if (!p.failed) {
        const empty = document.createElement('p');
        empty.className = 'help-text';
        empty.style.margin = '6px 0 0 16px';
        empty.textContent = <?= json_encode(t('user.takeoffs.autocount_other_page_none')) ?>;
        details.appendChild(empty);
      }

      list.appendChild(details);
    });

    const panel = document.getElementById('autocount-other-pages-panel');
    if (panel) panel.style.display = 'block';
  }

  document.getElementById('btn-run-autocount').addEventListener('click', () => {
    if (points.length < 2) return;
    const x = Math.min(points[0].x, points[1].x);
    const y = Math.min(points[0].y, points[1].y);
    const width = Math.abs(points[1].x - points[0].x);
    const height = Math.abs(points[1].y - points[0].y);

    const allPagesToggle = document.getElementById('autocount-all-pages-toggle');
    const searchAllPages = !!(allPagesToggle && allPagesToggle.checked);

    const btn = document.getElementById('btn-run-autocount');
    btn.disabled = true;
    const originalText = btn.textContent;
    btn.textContent = searchAllPages
      ? <?= json_encode(t('user.takeoffs.autocount_all_pages_searching')) ?>
      : <?= json_encode(t('user.takeoffs.autocount_searching')) ?>;

    const url = '/app/takeoffs/' + takeoffId + '/pages/' + pageId + (searchAllPages ? '/autocount-all-pages' : '/autocount');

    fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ _token: csrfToken, x, y, width, height })
    }).then(r => r.json()).then(data => {
      btn.disabled = false;
      btn.textContent = originalText;
      if (!data.ok) {
        alert(data.error || (searchAllPages
          ? <?= json_encode(t('user.takeoffs.autocount_all_pages_failed')) ?>
          : <?= json_encode(t('user.takeoffs.autocount_failed')) ?>));
        return;
      }

      document.getElementById('autocount-panel').style.display = 'none';
      mode = null;
      points = [];

      if (searchAllPages) {
        autocountAllPagesMode = true;
        const pagesResult = data.pages || [];
        const currentEntry = pagesResult.find(p => p.page_id === pageId);
        // Every detection starts confirmed — same "review to exclude wrong ones, not opt in
        // to right ones" posture as the single-page flow, applied per page here.
        autocountDetections = ((currentEntry && currentEntry.points) || []).map(p => ({ x: p.x, y: p.y, confirmed: true }));
        otherPagesResults = pagesResult.filter(p => p.page_id !== pageId).map(p => ({
          page_id: p.page_id,
          page_number: p.page_number,
          failed: p.failed,
          points: (p.points || []).map(pt => ({ x: pt.x, y: pt.y, confirmed: true })),
        }));
        renderAutocountReview();
        renderOtherPagesReview();
      } else {
        autocountDetections = (data.points || []).map(p => ({ x: p.x, y: p.y, confirmed: true }));
        renderAutocountReview();
      }
      draw();
    }).catch(() => {
      btn.disabled = false;
      btn.textContent = originalText;
      alert(searchAllPages
        ? <?= json_encode(t('user.takeoffs.autocount_all_pages_failed')) ?>
        : <?= json_encode(t('user.takeoffs.autocount_failed')) ?>);
    });
  });

  document.getElementById('btn-save-autocount').addEventListener('click', () => {
    const confirmed = autocountDetections.filter(d => d.confirmed);
    if (confirmed.length === 0) {
      alert(<?= json_encode(t('user.takeoffs.autocount_need_one')) ?>);
      return;
    }

    // After this save, it is an entirely ordinary 'count' TakeoffMeasurement — the exact
    // same addMeasurement() endpoint the Count tool itself posts to — so it renders and
    // edits/deletes identically to any manually-clicked count from here on, with no
    // "autocount" flag of its own.
    fetch('/app/takeoffs/' + takeoffId + '/pages/' + pageId + '/measurements', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({
        _token: csrfToken,
        type: 'count',
        label: <?= json_encode(t('user.takeoffs.autocount_default_label')) ?>,
        value: confirmed.length,
        unit_cost: 0,
        points: JSON.stringify(confirmed.map(d => ({ x: d.x, y: d.y }))),
        is_cutout: '0',
        parent_measurement_id: '',
        assembly_id: '',
      })
    }).then(r => r.json()).then(() => location.reload());
  });

  const btnSaveAutocountAllPages = document.getElementById('btn-save-autocount-all-pages');
  if (btnSaveAutocountAllPages) {
    btnSaveAutocountAllPages.addEventListener('click', () => {
      // Builds the { label, pages: [{page_id, points}, ...] } payload
      // saveAllPagesAutocount() expects — this page's own confirmed points (from
      // autocountDetections, the same array the canvas markers/toggle list above use) plus
      // every other page with at least one confirmed point; a page with zero confirmed
      // points (nothing confirmed, or the search failed on it) is simply left out.
      const pages = [];
      const confirmedHere = autocountDetections.filter(d => d.confirmed).map(d => ({ x: d.x, y: d.y }));
      if (confirmedHere.length > 0) {
        pages.push({ page_id: pageId, points: confirmedHere });
      }
      otherPagesResults.forEach(p => {
        if (p.failed) return;
        const confirmed = p.points.filter(d => d.confirmed).map(d => ({ x: d.x, y: d.y }));
        if (confirmed.length > 0) {
          pages.push({ page_id: p.page_id, points: confirmed });
        }
      });

      if (pages.length === 0) {
        alert(<?= json_encode(t('user.takeoffs.autocount_all_pages_need_one')) ?>);
        return;
      }

      fetch('/app/takeoffs/' + takeoffId + '/autocount-all-pages/save', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          _token: csrfToken,
          label: <?= json_encode(t('user.takeoffs.autocount_default_label')) ?>,
          pages: JSON.stringify(pages),
        })
      }).then(r => r.json()).then(() => location.reload());
    });
  }
})();
</script>

@endsection
