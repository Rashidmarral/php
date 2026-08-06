<?php use App\Core\View; ?>
<?php /** @var array $materials */ ?>
<div id="library-picker-overlay" style="display:none;position:fixed;inset:0;background:rgba(10,20,18,.5);z-index:1000;align-items:center;justify-content:center;">
  <div class="card" style="width:640px;max-width:92vw;max-height:80vh;display:flex;flex-direction:column;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;">📚 Pull from library</h3>
      <button type="button" id="library-picker-close" class="btn btn-sm btn-light">✕</button>
    </div>
    <?php if (empty($materials)): ?>
      <p class="help-text">Your <a href="/app/materials">Materials & Pricing Library</a> is empty — add items there first, or sync from Google Sheets.</p>
    <?php else: ?>
      <input type="text" id="library-picker-search" placeholder="Search materials or suppliers..." style="margin-bottom:10px;">
      <div id="library-picker-list" style="overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:6px;">
        <?php foreach ($materials as $m): ?>
          <button type="button" class="library-picker-item" data-desc="<?= View::e($m['name']) ?>" data-cost="<?= (float) $m['unit_cost'] ?>"
            data-search="<?= View::e(strtolower($m['name'] . ' ' . ($m['category'] ?? '') . ' ' . ($m['supplier_name'] ?? ''))) ?>"
            style="text-align:start;background:#fff;border:1px solid var(--border);border-radius:8px;padding:10px 12px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:10px;">
            <span>
              <strong style="display:block;font-size:13.5px;"><?= View::e($m['name']) ?></strong>
              <span class="help-text" style="font-size:12px;"><?= View::e($m['category'] ?: '—') ?> · <?= View::e($m['unit']) ?><?= $m['supplier_name'] ? ' · ' . View::e($m['supplier_name']) : '' ?></span>
              <span class="help-text" style="font-size:11.5px;">M <?= View::money((float)($m['material_cost'] ?? 0)) ?> + L <?= View::money((float)($m['labor_cost'] ?? 0)) ?></span>
            </span>
            <span class="badge badge-blue" style="white-space:nowrap;"><?= View::money((float)$m['unit_cost']) ?>/<?= View::e($m['unit']) ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function() {
  const overlay = document.getElementById('library-picker-overlay');
  if (!overlay) return;
  const openBtn = document.getElementById('open-library-picker');
  const closeBtn = document.getElementById('library-picker-close');
  const search = document.getElementById('library-picker-search');
  const list = document.getElementById('library-picker-list');

  if (openBtn) openBtn.addEventListener('click', () => { overlay.style.display = 'flex'; if (search) search.focus(); });
  closeBtn.addEventListener('click', () => { overlay.style.display = 'none'; });
  overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.style.display = 'none'; });

  if (search && list) {
    search.addEventListener('input', () => {
      const q = search.value.toLowerCase();
      list.querySelectorAll('.library-picker-item').forEach(btn => {
        btn.style.display = btn.dataset.search.includes(q) ? 'flex' : 'none';
      });
    });
  }

  if (list) {
    list.addEventListener('click', (e) => {
      const btn = e.target.closest('.library-picker-item');
      if (!btn) return;
      document.dispatchEvent(new CustomEvent('library-item-picked', {
        detail: { description: btn.dataset.desc, unitCost: parseFloat(btn.dataset.cost) || 0 }
      }));
      overlay.style.display = 'none';
    });
  }
})();
</script>
