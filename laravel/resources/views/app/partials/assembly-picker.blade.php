<?php /** @var array $assemblies */ ?>
{{--
  Mirrors app.partials.library-picker's structure and JS pattern exactly —
  server-renders the company's assemblies with their items already embedded
  as a data-items JSON attribute, so everything happens client-side with
  zero AJAX. The one difference: picking an assembly first asks for an
  applied quantity (labeled with the assembly's own base_uom) before it
  expands, since that's what scales every item's qty_per_unit into a real
  line-item qty. On confirm it dispatches `assembly-items-picked` with
  { items: [...] }, each item already scaled — _line-items.blade.php's JS
  listens for that event next to its existing `library-item-picked` one.
--}}
<div id="assembly-picker-overlay" style="display:none;position:fixed;inset:0;background:rgba(10,20,18,.5);z-index:1000;align-items:center;justify-content:center;">
  <div class="card" style="width:640px;max-width:92vw;max-height:80vh;display:flex;flex-direction:column;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
      <h3 style="margin:0;"><?= t('user.assembly_picker.title') ?></h3>
      <button type="button" id="assembly-picker-close" class="btn btn-sm btn-light">✕</button>
    </div>
    <?php if (empty($assemblies)): ?>
      <p class="help-text"><?= t('user.assembly_picker.empty_hint_prefix') ?> <a href="/app/assemblies"><?= t('user.assemblies.title') ?></a> <?= t('user.assembly_picker.empty_hint_suffix') ?></p>
    <?php else: ?>
      <input type="text" id="assembly-picker-search" placeholder="<?= t('user.assembly_picker.search_placeholder') ?>" style="margin-bottom:10px;">
      <div id="assembly-picker-list" style="overflow-y:auto;flex:1;display:flex;flex-direction:column;gap:6px;">
        <?php foreach ($assemblies as $a): ?>
          <?php
            $pickerItems = array_map(fn ($it) => [
                'description' => $it['description'],
                'descriptionAr' => $it['description_ar'] ?? '',
                'itemType' => $it['item_type'],
                'uom' => $it['uom'],
                'qtyPerUnit' => (float) $it['qty_per_unit'],
                'unitCost' => (float) $it['unit_cost'],
                'costCodeId' => $it['cost_code_id'],
            ], $a['items'] ?? []);
          ?>
          <div class="assembly-picker-item" data-search="<?= e(strtolower($a['name'] . ' ' . ($a['name_ar'] ?? ''))) ?>">
            <button type="button" class="assembly-picker-select" data-name="<?= e(local($a, 'name')) ?>" data-base-uom="<?= e($a['base_uom']) ?>" data-items='<?= e(json_encode($pickerItems)) ?>'
              style="width:100%;text-align:start;background:#fff;border:1px solid var(--border);border-radius:8px;padding:10px 12px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:10px;">
              <span>
                <strong style="display:block;font-size:13.5px;"><?= e(local($a, 'name')) ?></strong>
                <span class="help-text" style="font-size:12px;"><?= e($a['description'] ?: '—') ?></span>
              </span>
              <span class="badge badge-blue" style="white-space:nowrap;"><?= count($pickerItems) ?> <?= t('user.assembly_picker.items_suffix') ?> · <?= e($a['base_uom']) ?></span>
            </button>
            <div class="assembly-picker-qtystep" style="display:none;margin-top:6px;padding:10px 12px;background:var(--bg);border-radius:8px;gap:8px;align-items:center;">
              <label style="margin:0;white-space:nowrap;font-size:13px;" class="assembly-picker-qty-label"></label>
              <input type="number" class="assembly-picker-applied-qty" step="0.01" min="0.01" max="999999.99" inputmode="decimal" style="max-width:120px;">
              <button type="button" class="btn btn-sm btn-primary assembly-picker-confirm"><?= t('user.assembly_picker.confirm') ?></button>
              <button type="button" class="btn btn-sm btn-light assembly-picker-cancel"><?= t('common.cancel') ?></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<script>
(function() {
  const overlay = document.getElementById('assembly-picker-overlay');
  if (!overlay) return;
  const openBtn = document.getElementById('open-assembly-picker');
  const closeBtn = document.getElementById('assembly-picker-close');
  const search = document.getElementById('assembly-picker-search');
  const list = document.getElementById('assembly-picker-list');
  const qtyLabelTemplate = {!! json_encode(t('user.assembly_picker.applied_qty_label')) !!};

  function closeAllQtySteps() {
    if (!list) return;
    list.querySelectorAll('.assembly-picker-qtystep').forEach(step => { step.style.display = 'none'; });
  }

  if (openBtn) openBtn.addEventListener('click', () => { overlay.style.display = 'flex'; if (search) search.focus(); });
  closeBtn.addEventListener('click', () => { overlay.style.display = 'none'; closeAllQtySteps(); });
  overlay.addEventListener('click', (e) => { if (e.target === overlay) { overlay.style.display = 'none'; closeAllQtySteps(); } });

  if (search && list) {
    search.addEventListener('input', () => {
      const q = search.value.toLowerCase();
      list.querySelectorAll('.assembly-picker-item').forEach(row => {
        row.style.display = row.dataset.search.includes(q) ? '' : 'none';
      });
    });
  }

  if (list) {
    list.addEventListener('click', (e) => {
      const selectBtn = e.target.closest('.assembly-picker-select');
      if (selectBtn) {
        const step = selectBtn.closest('.assembly-picker-item').querySelector('.assembly-picker-qtystep');
        const alreadyOpen = step.style.display !== 'none';
        closeAllQtySteps();
        if (!alreadyOpen) {
          const baseUom = selectBtn.dataset.baseUom;
          step.querySelector('.assembly-picker-qty-label').textContent = qtyLabelTemplate.replace(':uom', baseUom);
          const input = step.querySelector('.assembly-picker-applied-qty');
          input.value = '';
          step.style.display = 'flex';
          input.focus();
        }
        return;
      }

      const cancelBtn = e.target.closest('.assembly-picker-cancel');
      if (cancelBtn) {
        closeAllQtySteps();
        return;
      }

      const confirmBtn = e.target.closest('.assembly-picker-confirm');
      if (confirmBtn) {
        const row = confirmBtn.closest('.assembly-picker-item');
        const selectButton = row.querySelector('.assembly-picker-select');
        const appliedQty = parseFloat(row.querySelector('.assembly-picker-applied-qty').value);
        if (!appliedQty || appliedQty <= 0) {
          return;
        }
        const sourceItems = JSON.parse(selectButton.dataset.items || '[]');
        const items = sourceItems.map(it => ({
          description: it.description,
          descriptionAr: it.descriptionAr,
          itemType: it.itemType,
          uom: it.uom,
          qty: Math.round(it.qtyPerUnit * appliedQty * 100) / 100,
          unitCost: it.unitCost,
          costCodeId: it.costCodeId,
        }));
        document.dispatchEvent(new CustomEvent('assembly-items-picked', { detail: { items } }));
        closeAllQtySteps();
        overlay.style.display = 'none';
      }
    });
  }
})();
</script>
