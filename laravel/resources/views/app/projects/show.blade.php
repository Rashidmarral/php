@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e(local($project, 'name')) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/projects/<?= $project['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <a href="/app/projects/<?= $project['id'] ?>/duplicate" class="btn btn-outline"><?= t('common.duplicate') ?></a>
    <?php $hasFinancialHistory = (count($invoices) + count($vendorBills)) > 0; ?>
    <form method="post" action="/app/projects/<?= $project['id'] ?>/delete" onsubmit="return confirm('<?= $hasFinancialHistory ? t('user.projects.delete_blocked_financial', ['invoices' => count($invoices), 'bills' => count($vendorBills)]) : t('user.projects.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi"><div class="label"><?= t('common.status') ?></div><div class="value" style="font-size:16px;"><span class="badge badge-blue"><?= e(str_replace('_',' ',$project['status'])) ?></span></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.original_budget') ?></div><div class="value"><?= money((float)$project['budget']) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.revised_budget') ?></div><div class="value"><?= money((float)$project['budget'] + $approvedChangeOrdersTotal) ?></div></div>
  <div class="kpi"><div class="label"><?= t('user.projects.start_end') ?></div><div class="value" style="font-size:16px;"><?= e($project['start_date'] ?: '—') ?> → <?= e($project['end_date'] ?: '—') ?></div></div>
</div>

<div class="grid grid-2">
  <div class="card">
    <h3><?= t('user.projects.estimates') ?></h3>
    <?php if (empty($estimates)): ?><p class="help-text"><?= t('user.projects.no_estimates_linked') ?></p><?php else: ?>
      <table class="data"><thead><tr><th><?= t('common.title') ?></th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead><tbody>
      <?php foreach ($estimates as $e): ?>
        <tr><td><a href="/app/estimates/<?= $e['id'] ?>"><?= e($e['title']) ?></a></td><td><span class="badge badge-gray"><?= e($e['status']) ?></span></td><td><?= money((float)$e['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
  <div class="card">
    <h3><?= t('user.projects.invoices') ?></h3>
    <?php if (empty($invoices)): ?><p class="help-text"><?= t('user.projects.no_invoices_linked') ?></p><?php else: ?>
      <table class="data"><thead><tr><th>#</th><th><?= t('common.status') ?></th><th><?= t('common.total') ?></th></tr></thead><tbody>
      <?php foreach ($invoices as $inv): ?>
        <tr><td><a href="/app/invoices/<?= $inv['id'] ?>"><?= e($inv['invoice_number']) ?></a></td><td><span class="badge badge-<?= $inv['status']==='paid'?'green':'yellow' ?>"><?= e($inv['status']) ?></span></td><td><?= money((float)$inv['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
  </div>
</div>

<?php if ($client && !empty($client['phone'])): ?>
<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.whatsapp_update_title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.whatsapp_update_hint') ?></p>
  <form style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-top:10px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:240px;">
      <label><?= t('user.projects.whatsapp_update_note') ?></label>
      <input type="text" name="note" placeholder="<?= t('user.projects.whatsapp_update_note_placeholder') ?>">
    </div>
    <button type="submit" formmethod="get" formaction="/app/projects/<?= $project['id'] ?>/share-progress" formtarget="_blank" class="btn btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('common.send_whatsapp') ?></button>
    <?php if ($whatsappApiConfigured): ?>
      <button type="submit" formmethod="post" formaction="/app/projects/<?= $project['id'] ?>/send-whatsapp-progress" class="btn btn-outline">🤖 <?= t('user.projects.auto_notify_whatsapp') ?></button>
    <?php endif; ?>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.change_orders') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.change_orders_hint') ?></p>

  <?php if (!empty($changeOrders)): ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('common.title') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($changeOrders as $co): ?>
        <tr>
          <td><?= e(local($co, 'title')) ?><?php if ($co['description']): ?><br><span class="help-text"><?= e(local($co, 'description')) ?></span><?php endif; ?></td>
          <td><?= (float)$co['amount'] >= 0 ? '+' : '' ?><?= money((float)$co['amount']) ?></td>
          <td><span class="badge badge-<?= $co['status']==='approved'?'green':($co['status']==='rejected'?'red':'yellow') ?>"><?= e(ucfirst($co['status'])) ?></span></td>
          <td style="display:flex;gap:6px;">
            <?php if ($co['status'] === 'pending'): ?>
              <form method="post" action="/app/change-orders/<?= $co['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="approved">
                <button type="submit" class="btn btn-sm btn-primary"><?= t('common.approve') ?></button>
              </form>
              <form method="post" action="/app/change-orders/<?= $co['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="rejected">
                <button type="submit" class="btn btn-sm btn-light"><?= t('common.reject') ?></button>
              </form>
            <?php endif; ?>
            <form method="post" action="/app/change-orders/<?= $co['id'] ?>/delete" onsubmit="return confirm('<?= t('user.projects.remove_co_confirm') ?>');" style="display:inline;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/change-orders" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('common.title_en') ?></label><input type="text" name="title" placeholder="e.g. Additional glazing" required></div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('common.title_ar') ?></label><input type="text" name="title_ar" dir="rtl" placeholder="العنوان بالعربية"></div>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('user.projects.amount_sar') ?></label><input type="number" step="0.01" name="amount" placeholder="e.g. 15000 or -5000" required></div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label><?= t('common.description_en') ?></label><input type="text" name="description"></div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label><?= t('common.description_ar') ?></label><input type="text" name="description_ar" dir="rtl"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.projects.add_change_order') ?></button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.purchase_orders.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.purchase_orders.hint') ?></p>

  <?php if (!empty($purchaseOrders)): ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('common.status') ?></th><th>#</th><th><?= t('common.supplier') ?></th><th><?= t('user.projects.issue_date') ?></th><th><?= t('common.total') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($purchaseOrders as $po):
        $poSupplier = collect($suppliers)->firstWhere('id', $po['supplier_id']);
        $poStatusBadge = ['draft' => 'gray', 'issued' => 'blue', 'received' => 'green', 'cancelled' => 'red'][$po['status']] ?? 'gray';
      ?>
        <tr>
          <td><span class="badge badge-<?= $poStatusBadge ?>"><?= e($purchaseOrderStatuses[$po['status']] ?? ucfirst($po['status'])) ?></span></td>
          <td><?= e($po['po_number']) ?></td>
          <td><?= e($poSupplier['name'] ?? '—') ?></td>
          <td><?= e($po['issue_date'] ?: '—') ?></td>
          <td><?= money((float)$po['total']) ?></td>
          <td style="display:flex;gap:6px;flex-wrap:wrap;">
            <a href="/app/purchase-orders/<?= $po['id'] ?>/pdf" class="btn btn-sm btn-light" target="_blank">⬇ <?= t('common.download_pdf') ?></a>
            <?php if (!empty($poWhatsappLinks[$po['id']] ?? null)): ?>
              <a href="<?= e($poWhatsappLinks[$po['id']]) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-light" style="background:#25D366;color:#fff;border-color:#25D366;">💬 <?= t('common.send_whatsapp') ?></a>
              <?php if ($whatsappApiConfigured): ?>
                <form method="post" action="/app/purchase-orders/<?= $po['id'] ?>/send-whatsapp" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-outline">🤖 <?= t('user.purchase_orders.auto_notify_whatsapp') ?></button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
            <?php if ($po['status'] === 'draft'): ?>
              <form method="post" action="/app/purchase-orders/<?= $po['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="issued">
                <button type="submit" class="btn btn-sm btn-primary"><?= t('user.purchase_orders.issue') ?></button>
              </form>
              <form method="post" action="/app/purchase-orders/<?= $po['id'] ?>/delete" onsubmit="return confirm('<?= t('user.purchase_orders.remove_confirm') ?>');" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
              </form>
            <?php elseif ($po['status'] === 'issued'): ?>
              <form method="post" action="/app/purchase-orders/<?= $po['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="received">
                <button type="submit" class="btn btn-sm btn-primary"><?= t('user.purchase_orders.mark_received') ?></button>
              </form>
              <form method="post" action="/app/purchase-orders/<?= $po['id'] ?>/status" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
                <button type="submit" class="btn btn-sm btn-light"><?= t('common.cancel') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="help-text"><?= t('user.purchase_orders.none_yet') ?></p>
  <?php endif; ?>

  <a href="/app/projects/<?= $project['id'] ?>/purchase-orders/create" class="btn btn-outline"><?= t('user.purchase_orders.add_po') ?></a>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.bank_guarantees') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.bank_guarantees_hint') ?></p>

  <?php if (!empty($bankGuarantees)): ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('common.type') ?></th><th><?= t('user.projects.bank_name') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.expiry') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($bankGuarantees as $bg):
        $daysLeft = $bg['expiry_date'] ? (int) ceil((strtotime($bg['expiry_date']) - strtotime(date('Y-m-d'))) / 86400) : null;
        if ($bg['status'] !== 'active') { $expiryBadge = 'gray'; $expiryLabel = $bg['expiry_date'] ?: '—'; }
        elseif ($daysLeft === null) { $expiryBadge = 'gray'; $expiryLabel = t('user.business_setup.no_expiry_set'); }
        elseif ($daysLeft < 0) { $expiryBadge = 'red'; $expiryLabel = t('user.business_setup.expired'); }
        elseif ($daysLeft <= 30) { $expiryBadge = 'red'; $expiryLabel = t('user.business_setup.days_left', ['days' => $daysLeft]); }
        elseif ($daysLeft <= 60) { $expiryBadge = 'yellow'; $expiryLabel = t('user.business_setup.days_left', ['days' => $daysLeft]); }
        else { $expiryBadge = 'green'; $expiryLabel = t('user.business_setup.days_left', ['days' => $daysLeft]); }
        $statusBadge = ['active' => 'blue', 'released' => 'green', 'claimed' => 'red', 'expired' => 'gray'][$bg['status']] ?? 'gray';
      ?>
        <tr>
          <td><?= e($bankGuaranteeTypes[$bg['type']] ?? ucfirst($bg['type'])) ?><?php if ($bg['guarantee_number']): ?><br><span class="help-text">#<?= e($bg['guarantee_number']) ?></span><?php endif; ?></td>
          <td><?= e($bg['bank_name'] ?: '—') ?><?php if ($bg['file_path']): ?> · <a href="<?= e($bg['file_path']) ?>" target="_blank"><?= t('user.business_setup.file_link') ?></a><?php endif; ?></td>
          <td><?= money((float)$bg['amount']) ?></td>
          <td><span class="badge badge-<?= $expiryBadge ?>"><?= e($expiryLabel) ?></span></td>
          <td><span class="badge badge-<?= $statusBadge ?>"><?= e(ucfirst($bg['status'])) ?></span></td>
          <td style="display:flex;gap:6px;">
            <?php if ($bg['status'] === 'active'): ?>
              <form method="post" action="/app/bank-guarantees/<?= $bg['id'] ?>" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="released">
                <button type="submit" class="btn btn-sm btn-primary"><?= t('user.projects.mark_released') ?></button>
              </form>
              <form method="post" action="/app/bank-guarantees/<?= $bg['id'] ?>" style="display:inline;">
                <?= csrf_field() ?><input type="hidden" name="status" value="claimed">
                <button type="submit" class="btn btn-sm btn-light"><?= t('user.projects.mark_claimed') ?></button>
              </form>
            <?php endif; ?>
            <form method="post" action="/app/bank-guarantees/<?= $bg['id'] ?>/delete" onsubmit="return confirm('<?= t('user.projects.remove_guarantee_confirm') ?>');" style="display:inline;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/bank-guarantees" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;width:190px;"><label><?= t('user.projects.guarantee_type') ?></label>
      <select name="type">
        <?php foreach ($bankGuaranteeTypes as $key => $label): ?><option value="<?= $key ?>"><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;flex:1;min-width:160px;"><label><?= t('user.projects.bank_name') ?></label><input type="text" name="bank_name" placeholder="e.g. Al Rajhi Bank" required></div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.projects.guarantee_number') ?></label><input type="text" name="guarantee_number"></div>
    <div class="form-group" style="margin:0;width:140px;"><label><?= t('user.projects.amount_sar') ?></label><input type="number" step="0.01" min="0.01" name="amount" required></div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.projects.issue_date') ?></label><input type="date" name="issue_date"></div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.business_setup.expiry_date') ?></label><input type="date" name="expiry_date"></div>
    <div class="form-group" style="margin:0;min-width:180px;"><label><?= t('user.business_setup.upload_optional') ?></label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.projects.add_guarantee') ?></button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.budget_vs_actual_title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.budget_vs_actual_hint') ?></p>

  <div class="kpi-grid" style="grid-template-columns:repeat(4,1fr);margin-top:12px;">
    <div class="kpi"><div class="label"><?= t('user.projects.revised_budget') ?></div><div class="value" style="font-size:20px;"><?= money($revisedBudget) ?></div></div>
    <div class="kpi"><div class="label"><?= t('user.purchase_orders.committed') ?></div><div class="value" style="font-size:20px;"><?= money($committedTotal) ?></div></div>
    <div class="kpi"><div class="label"><?= t('user.projects.actual_spent') ?></div><div class="value" style="font-size:20px;"><?= money($actualCostTotal) ?></div></div>
    <div class="kpi"><div class="label"><?= $availableBudget >= 0 ? t('user.purchase_orders.available') : t('user.purchase_orders.over_budget') ?></div><div class="value" style="font-size:20px;color:<?= $availableBudget < 0 ? 'var(--danger)' : 'var(--brand-dark)' ?>;"><?= money(abs($availableBudget)) ?></div></div>
  </div>
  <div style="background:var(--bg);border-radius:8px;height:10px;overflow:hidden;margin:14px 0 6px;">
    <div style="background:<?= $availableBudget < 0 ? 'var(--danger)' : 'linear-gradient(90deg,var(--brand),var(--brand-dark))' ?>;height:100%;width:<?= min(100, $budgetUsedPercent) ?>%;"></div>
  </div>
  <p class="help-text"><?= t('user.projects.budget_used_note', ['percent' => $budgetUsedPercent]) ?><?php if ($committedTotal > 0): ?> <?= t('user.projects.budget_used_committed_note', ['amount' => money($committedTotal)]) ?><?php endif; ?></p>
  <?php if ($availableBudget < 0): ?>
    <p class="help-text" style="color:var(--danger);font-weight:600;">⚠ <?= t('user.projects.over_budget_warning') ?></p>
  <?php endif; ?>

  <?php if (!empty($vendorBills)): ?>
    <table class="data" style="margin:16px 0;">
      <thead><tr><th><?= t('common.description') ?></th><th><?= t('common.category') ?></th><th><?= t('common.date') ?></th><th><?= t('common.amount') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($vendorBills as $vb):
        $vbPo = $vb['purchase_order_id'] ? collect($purchaseOrders)->firstWhere('id', $vb['purchase_order_id']) : null;
      ?>
        <tr>
          <td><?= e($vb['description']) ?><?php if ($vb['reference']): ?><br><span class="help-text"><?= e($vb['reference']) ?></span><?php endif; ?><?php if ($vbPo): ?><br><span class="help-text"><?= t('user.purchase_orders.linked_po') ?>: <?= e($vbPo['po_number']) ?></span><?php endif; ?></td>
          <td><span class="badge badge-gray"><?= e(ucfirst($vb['category'])) ?></span></td>
          <td><?= e($vb['bill_date']) ?></td>
          <td><?= money((float)$vb['amount']) ?></td>
          <td><span class="badge badge-<?= $vb['status']==='paid'?'green':'yellow' ?>"><?= e(ucfirst($vb['status'])) ?></span></td>
          <td>
            <?php if ($vb['file_path']): ?><a href="<?= e($vb['file_path']) ?>" target="_blank" class="btn btn-sm btn-light">📎</a><?php endif; ?>
            <form method="post" action="/app/vendor-bills/<?= $vb['id'] ?>/delete" onsubmit="return confirm('<?= t('user.projects.remove_vendor_bill_confirm') ?>');" style="display:inline;">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger">✕</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/vendor-bills" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label><?= t('common.description') ?></label><input type="text" name="description" placeholder="e.g. Rebar delivery — invoice #4521" required></div>
    <div class="form-group" style="margin:0;width:140px;"><label><?= t('common.category') ?></label>
      <select name="category">
        <?php foreach (\App\Models\VendorBill::CATEGORIES as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('common.supplier') ?></label>
      <select name="supplier_id">
        <option value="">—</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('user.purchase_orders.linked_po') ?></label>
      <select name="purchase_order_id">
        <option value="">—</option>
        <?php foreach ($purchaseOrders as $po): if ($po['status'] !== 'issued') continue; ?>
          <option value="<?= $po['id'] ?>"><?= e($po['po_number']) ?> (<?= money((float)$po['total']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:130px;"><label><?= t('user.projects.amount_sar') ?></label><input type="number" step="0.01" min="0.01" name="amount" required></div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.projects.bill_date') ?></label><input type="date" name="bill_date"></div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.projects.reference_number') ?></label><input type="text" name="reference" placeholder="<?= t('user.projects.reference_placeholder') ?>"></div>
    <div class="form-group" style="margin:0;min-width:180px;"><label><?= t('user.projects.receipt_optional') ?></label><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.projects.add_vendor_bill') ?></button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.equipment.project_card_title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.equipment.project_card_hint') ?></p>

  <?php if (empty($projectEquipment)): ?>
    <p class="help-text"><?= t('user.equipment.none_assigned') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('common.name') ?></th><th><?= t('user.equipment.assigned_date') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($projectEquipment as $pe): ?>
        <tr>
          <td><a href="/app/equipment/<?= $pe['equipment_id'] ?>"><?= e($pe['equipment_name']) ?></a></td>
          <td><?= e($pe['assigned_date'] ?: '—') ?></td>
          <td>
            <form method="post" action="/app/equipment-assignments/<?= $pe['id'] ?>/return" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="from" value="project">
              <button type="submit" class="btn btn-sm btn-outline"><?= t('user.equipment.return_btn') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/equipment-assignments" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;">
      <label><?= t('user.equipment.title') ?></label>
      <select name="equipment_id" required>
        <option value=""><?= t('user.equipment.select_equipment') ?></option>
        <?php foreach ($availableEquipment as $eq): ?><option value="<?= $eq['id'] ?>"><?= e(local($eq, 'name')) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('user.equipment.assigned_date') ?></label><input type="date" name="assigned_date" value="<?= date('Y-m-d') ?>"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.equipment.assign_btn') ?></button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.site_log.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.site_log.card_hint') ?></p>

  <?php if (empty($siteLogs)): ?>
    <p class="help-text"><?= t('user.site_log.no_entries_hint') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('user.site_log.weather') ?></th><th><?= t('user.site_log.workers_on_site') ?></th><th><?= t('user.site_log.notes') ?></th></tr></thead>
      <tbody>
      <?php foreach ($siteLogs as $log): ?>
        <tr>
          <td><?= e($log['log_date']) ?></td>
          <td><?= e($log['weather'] ?: '—') ?></td>
          <td><?= $log['workers_on_site'] !== null ? e((string)$log['workers_on_site']) : '—' ?></td>
          <td><?= e(\Illuminate\Support\Str::limit((string)$log['notes'], 80)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <a href="/app/projects/<?= $project['id'] ?>/site-log" class="btn btn-outline">
    <?= $siteLogCount > 0 ? t('user.site_log.view_all', ['count' => $siteLogCount]) : t('user.site_log.add_entry') ?>
  </a>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.payment_certificates.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.payment_certificates.card_hint') ?></p>

  <div class="kpi-grid" style="margin-bottom:14px;">
    <div class="kpi"><div class="label"><?= t('user.boq.contract_value') ?></div><div class="value" style="font-size:18px;"><?= money($boqContractValue) ?></div></div>
    <div class="kpi"><div class="label"><?= t('user.payment_certificates.cumulative') ?></div><div class="value" style="font-size:18px;"><?= money($cumulativeCertified) ?></div></div>
  </div>

  <?php if (empty($paymentCertificates)): ?>
    <p class="help-text"><?= t('user.payment_certificates.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th><?= t('user.payment_certificates.number') ?></th><th><?= t('user.payment_certificates.date') ?></th><th><?= t('common.status') ?></th><th><?= t('user.payment_certificates.gross') ?></th><th><?= t('user.payment_certificates.net_payable') ?></th></tr></thead>
      <tbody>
      <?php foreach ($paymentCertificates as $cert): ?>
        <tr>
          <td><a href="/app/payment-certificates/<?= $cert['id'] ?>">#<?= $cert['certificate_number'] ?></a></td>
          <td><?= e($cert['certificate_date']) ?></td>
          <td><span class="badge <?= $cert['status'] === 'certified' ? 'badge-green' : 'badge-gray' ?>"><?= e($cert['status']) ?></span></td>
          <td><?= money((float)$cert['gross_amount']) ?></td>
          <td><?= money((float)$cert['net_payable']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="/app/projects/<?= $project['id'] ?>/boq" class="btn btn-outline"><?= t('user.boq.manage') ?></a>
    <a href="/app/projects/<?= $project['id'] ?>/payment-certificates" class="btn btn-outline">
      <?= $paymentCertificateCount > 0 ? t('user.payment_certificates.view_all', ['count' => $paymentCertificateCount]) : t('user.payment_certificates.title') ?>
    </a>
    <a href="/app/projects/<?= $project['id'] ?>/payment-certificates/create" class="btn btn-primary"><?= t('user.payment_certificates.new') ?></a>
  </div>

  <?php if ($paymentCertificateCount > 0 || $retentionHeld > 0): ?>
    <?php $dlpPassed = !empty($project['defects_liability_end_date']) && $project['defects_liability_end_date'] < date('Y-m-d'); ?>
    <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px;">
      <div class="kpi-grid" style="margin-bottom:10px;">
        <div class="kpi">
          <div class="label"><?= t('user.projects.retention_held') ?></div>
          <div class="value" style="font-size:18px;"><?= money($retentionHeld) ?></div>
        </div>
        <div class="kpi">
          <div class="label"><?= t('user.projects.defects_liability_end_date') ?></div>
          <div class="value" style="font-size:18px;">
            <?= $project['defects_liability_end_date'] ? e($project['defects_liability_end_date']) : '—' ?>
            <?php if ($dlpPassed): ?> <span class="badge badge-red"><?= t('user.projects.retention_release_due') ?></span><?php endif; ?>
          </div>
        </div>
      </div>
      <?php if ($retentionHeld > 0): ?>
        <form method="post" action="/app/projects/<?= $project['id'] ?>/retention/release-all" onsubmit="return confirm('<?= t('user.projects.release_all_retention_confirm') ?>');">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline"><?= t('user.projects.release_all_retention') ?></button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.subcontracts.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.subcontracts.card_hint') ?></p>

  <div class="kpi-grid" style="margin-bottom:14px;">
    <div class="kpi"><div class="label"><?= t('user.subcontracts.contract_value') ?></div><div class="value" style="font-size:18px;"><?= money($subcontractsContractValue) ?></div></div>
    <div class="kpi"><div class="label"><?= t('user.subcontracts.cumulative_paid') ?></div><div class="value" style="font-size:18px;"><?= money($subcontractsCumulativePaid) ?></div></div>
    <div class="kpi"><div class="label"><?= t('user.subcontracts.retention_held') ?></div><div class="value" style="font-size:18px;"><?= money($subcontractsRetentionHeld) ?></div></div>
  </div>

  <?php if (empty($subcontracts)): ?>
    <p class="help-text"><?= t('user.subcontracts.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th><?= t('common.title') ?></th><th><?= t('user.subcontracts.subcontractor') ?></th><th><?= t('common.status') ?></th><th><?= t('user.subcontracts.contract_value') ?></th></tr></thead>
      <tbody>
      <?php foreach ($subcontracts as $s): ?>
        <tr>
          <td><a href="/app/subcontracts/<?= $s['id'] ?>"><?= e($s['title']) ?></a></td>
          <td><?= e($s['supplier_name']) ?></td>
          <td><span class="badge <?= $s['status'] === 'active' ? 'badge-green' : ($s['status'] === 'terminated' ? 'badge-red' : 'badge-gray') ?>"><?= e($subcontractStatuses[$s['status']] ?? ucfirst($s['status'])) ?></span></td>
          <td><?= money((float)$s['contract_value']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <a href="/app/projects/<?= $project['id'] ?>/subcontracts" class="btn btn-outline">
      <?= $subcontractCount > 0 ? t('user.subcontracts.view_all', ['count' => $subcontractCount]) : t('user.subcontracts.title') ?>
    </a>
    <a href="/app/projects/<?= $project['id'] ?>/subcontracts/create" class="btn btn-primary"><?= t('user.subcontracts.new') ?></a>
  </div>
</div>

<?php if (!empty($project['end_date']) || !empty($eotRequests)): ?>
<div class="card" style="margin-top:24px;">
  <h3><?= t('user.ld_eot.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.ld_eot.card_hint') ?></p>

  <?php if (!empty($project['end_date'])): ?>
    <div class="kpi-grid" style="margin-bottom:14px;">
      <div class="kpi"><div class="label"><?= t('user.ld_eot.contract_end_date') ?></div><div class="value" style="font-size:18px;"><?= e($project['end_date']) ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.ld_eot.approved_eot_days') ?></div><div class="value" style="font-size:18px;"><?= (int)$approvedEotDays ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.ld_eot.effective_completion_date') ?></div><div class="value" style="font-size:18px;"><?= $ldExposure['effectiveCompletionDate'] ? e($ldExposure['effectiveCompletionDate']) : '—' ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.ld_eot.delay_days') ?></div><div class="value" style="font-size:18px;"><?= (int)$ldExposure['delayDays'] ?></div></div>
      <div class="kpi"><div class="label"><?= t('user.ld_eot.ld_exposure') ?></div><div class="value" style="font-size:18px;"><?= money($ldExposure['cappedLdAmount']) ?></div></div>
    </div>
    <?php if ($ldExposure['isCapped']): ?>
      <p class="help-text" style="margin-bottom:10px;"><?= t('user.ld_eot.capped_note', ['percent' => e((string)$project['ld_cap_percent']), 'raw' => money($ldExposure['rawLdAmount'])]) ?></p>
    <?php endif; ?>
    <div class="alert" style="max-width:820px;background:#eef3fb;color:#2c5282;border:1px solid #bcd6f2;margin-bottom:16px;">
      <?= t('user.ld_eot.disclaimer') ?>
    </div>
  <?php endif; ?>

  <h4 style="margin-bottom:4px;"><?= t('user.ld_eot.requests_title') ?></h4>
  <?php if (empty($eotRequests)): ?>
    <p class="help-text"><?= t('user.ld_eot.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th><?= t('user.ld_eot.days_requested') ?></th><th><?= t('user.ld_eot.reason') ?></th><th><?= t('common.status') ?></th><th><?= t('user.ld_eot.requested_by') ?></th><th><?= t('user.ld_eot.reviewed_by') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($eotRequests as $eot): ?>
        <tr>
          <td><?= (int)$eot['requested_days'] ?></td>
          <td><?= e(\Illuminate\Support\Str::limit((string)$eot['reason'], 80)) ?></td>
          <td><span class="badge <?= ['approved'=>'badge-green','rejected'=>'badge-red'][$eot['status']] ?? 'badge-gray' ?>"><?= e($eotStatuses[$eot['status']] ?? ucfirst($eot['status'])) ?></span></td>
          <td><?= e($eot['requested_by_name']) ?></td>
          <td><?= e($eot['reviewed_by_name'] ?? '—') ?></td>
          <td>
            <?php if ($eot['status'] === 'pending'): ?>
              <?php if (auth()->user()->can('approve_documents')): ?>
                <form method="post" action="/app/extension-of-time/<?= $eot['id'] ?>/approve" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-primary"><?= t('common.approve') ?></button>
                </form>
                <form method="post" action="/app/extension-of-time/<?= $eot['id'] ?>/reject" style="display:inline;">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-sm btn-danger"><?= t('common.reject') ?></button>
                </form>
              <?php endif; ?>
              <form method="post" action="/app/extension-of-time/<?= $eot['id'] ?>/delete" onsubmit="return confirm('<?= t('user.ld_eot.withdraw_confirm') ?>');" style="display:inline;">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline"><?= t('user.ld_eot.withdraw') ?></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/extension-of-time" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.ld_eot.days_requested') ?></label><input type="number" min="1" step="1" name="requested_days" required></div>
    <div class="form-group" style="margin:0;flex:1;min-width:220px;"><label><?= t('user.ld_eot.reason') ?></label><input type="text" name="reason" placeholder="<?= t('user.ld_eot.reason_placeholder') ?>" required></div>
    <button type="submit" class="btn btn-outline"><?= t('user.ld_eot.submit_request') ?></button>
  </form>
</div>
<?php endif; ?>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.punch_list.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.punch_list.hint') ?></p>

  <?php if (!empty($punchListItems)): ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th><?= t('common.title') ?></th><th><?= t('user.punch_list.location') ?></th><th><?= t('user.punch_list.priority') ?></th><th><?= t('user.punch_list.assigned_to') ?></th><th><?= t('user.punch_list.due_date') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($punchListItems as $item):
        $priorityBadge = ['low' => 'gray', 'medium' => 'yellow', 'high' => 'red'][$item['priority']] ?? 'gray';
        $statusBadge = ['open' => 'red', 'in_progress' => 'yellow', 'resolved' => 'green'][$item['status']] ?? 'gray';
        $overdue = $item['due_date'] && $item['status'] !== 'resolved' && $item['due_date'] < date('Y-m-d');
      ?>
        <tr>
          <td>
            <?= e($item['title']) ?>
            <?php if ($item['description']): ?><br><span class="help-text"><?= e($item['description']) ?></span><?php endif; ?>
            <?php if ($item['photo_path']): ?><br><a href="<?= e($item['photo_path']) ?>" target="_blank"><?= t('user.punch_list.view_photo') ?></a><?php endif; ?>
          </td>
          <td><?= e($item['location'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $priorityBadge ?>"><?= e($punchListPriorities[$item['priority']] ?? ucfirst($item['priority'])) ?></span></td>
          <td>
            <form method="post" action="/app/punch-list/<?= $item['id'] ?>" style="display:inline;">
              <?= csrf_field() ?>
              <select name="assigned_to" onchange="this.form.submit()" style="padding:4px 6px;">
                <option value=""><?= t('user.punch_list.unassigned') ?></option>
                <?php foreach ($teamMembers as $member): ?>
                  <option value="<?= $member['id'] ?>" <?= (int)($item['assigned_to'] ?? 0) === (int)$member['id'] ? 'selected' : '' ?>><?= e($member['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td><?= e($item['due_date'] ?: '—') ?><?php if ($overdue): ?> <span class="badge badge-red"><?= t('user.punch_list.overdue') ?></span><?php endif; ?></td>
          <td>
            <form method="post" action="/app/punch-list/<?= $item['id'] ?>" style="display:inline;">
              <?= csrf_field() ?>
              <select name="status" onchange="this.form.submit()" class="badge badge-<?= $statusBadge ?>" style="border:none;padding:4px 8px;">
                <?php foreach ($punchListStatuses as $key => $label): ?>
                  <option value="<?= $key ?>" <?= $item['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <form method="post" action="/app/punch-list/<?= $item['id'] ?>/delete" onsubmit="return confirm('<?= t('user.punch_list.remove_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/punch-list" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('common.title') ?></label><input type="text" name="title" placeholder="e.g. Chipped tile, lobby floor" required></div>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('user.punch_list.location') ?></label><input type="text" name="location" placeholder="e.g. 2nd floor, unit 204"></div>
    <div class="form-group" style="margin:0;width:130px;"><label><?= t('user.punch_list.priority') ?></label>
      <select name="priority">
        <?php foreach ($punchListPriorities as $key => $label): ?><option value="<?= $key ?>" <?= $key==='medium'?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:170px;"><label><?= t('user.punch_list.assigned_to') ?></label>
      <select name="assigned_to">
        <option value=""><?= t('user.punch_list.unassigned') ?></option>
        <?php foreach ($teamMembers as $member): ?><option value="<?= $member['id'] ?>"><?= e($member['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.punch_list.due_date') ?></label><input type="date" name="due_date"></div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label><?= t('common.description_en') ?></label><input type="text" name="description"></div>
    <div class="form-group" style="margin:0;min-width:170px;"><label><?= t('user.punch_list.photo_optional') ?></label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.punch_list.add_item') ?></button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.safety.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.safety.hint') ?></p>

  <h4 style="font-size:14px;margin-bottom:8px;"><?= t('user.safety_incidents.title') ?></h4>
  <?php if (empty($safetyIncidents)): ?>
    <p class="help-text"><?= t('user.safety_incidents.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th>#</th><th><?= t('user.safety_incidents.type') ?></th><th><?= t('user.safety_incidents.severity') ?></th><th><?= t('common.date') ?></th><th><?= t('common.status') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($safetyIncidents as $incident):
        $severityBadge = ['low' => 'gray', 'medium' => 'yellow', 'high' => 'red', 'critical' => 'red'][$incident['severity']] ?? 'gray';
        $incidentStatusBadge = ['open' => 'red', 'under_investigation' => 'yellow', 'closed' => 'green'][$incident['status']] ?? 'gray';
      ?>
        <tr>
          <td>INC-<?= str_pad((string)$incident['incident_number'], 3, '0', STR_PAD_LEFT) ?></td>
          <td>
            <?= e($incident['incident_type']) ?>
            <?php if ($incident['photo_path']): ?><br><a href="<?= e($incident['photo_path']) ?>" target="_blank"><?= t('user.punch_list.view_photo') ?></a><?php endif; ?>
          </td>
          <td><span class="badge badge-<?= $severityBadge ?>"><?= e($safetyIncidentSeverities[$incident['severity']] ?? ucfirst($incident['severity'])) ?></span></td>
          <td><?= e($incident['incident_date']) ?></td>
          <td>
            <form method="post" action="/app/safety-incidents/<?= $incident['id'] ?>/status" style="display:inline;">
              <?= csrf_field() ?>
              <select name="status" onchange="this.form.submit()" class="badge badge-<?= $incidentStatusBadge ?>" style="border:none;padding:4px 8px;">
                <?php foreach ($safetyIncidentStatuses as $key => $label): ?>
                  <option value="<?= $key ?>" <?= $incident['status'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </td>
          <td>
            <form method="post" action="/app/safety-incidents/<?= $incident['id'] ?>/delete" onsubmit="return confirm('<?= t('user.safety_incidents.remove_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?php if ($safetyIncidentCount > count($safetyIncidents)): ?>
    <p style="margin:0 0 12px;"><a href="/app/projects/<?= $project['id'] ?>/safety-incidents"><?= t('user.safety_incidents.view_all', ['count' => $safetyIncidentCount]) ?></a></p>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/safety-incidents" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('common.date') ?></label><input type="date" name="incident_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;width:170px;"><label><?= t('user.safety_incidents.type') ?></label>
      <select name="incident_type" class="safety-incident-type-select" required>
        <?php foreach ($safetyIncidentSuggestedTypes as $type): ?><option value="<?= e($type) ?>"><?= e($type) ?></option><?php endforeach; ?>
        <option value="other"><?= t('user.safety_incidents.other_type') ?></option>
      </select>
      <input type="text" name="incident_type_other" class="safety-incident-type-other" placeholder="<?= t('user.safety_incidents.other_type_placeholder') ?>" style="display:none;margin-top:6px;">
    </div>
    <div class="form-group" style="margin:0;width:130px;"><label><?= t('user.safety_incidents.severity') ?></label>
      <select name="severity">
        <?php foreach ($safetyIncidentSeverities as $key => $label): ?><option value="<?= $key ?>" <?= $key==='low'?'selected':'' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:150px;"><label><?= t('user.punch_list.location') ?></label><input type="text" name="location" placeholder="e.g. 3rd floor"></div>
    <div class="form-group" style="margin:0;width:170px;"><label><?= t('user.safety_incidents.injured_person') ?></label><input type="text" name="injured_person_name"></div>
    <div class="form-group" style="margin:0;flex:2;min-width:220px;"><label><?= t('common.description') ?></label><input type="text" name="description" required></div>
    <div class="form-group" style="margin:0;min-width:170px;"><label><?= t('user.punch_list.photo_optional') ?></label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.safety_incidents.log_incident') ?></button>
  </form>

  <h4 style="font-size:14px;margin-bottom:8px;"><?= t('user.toolbox_talks.title') ?></h4>
  <?php if (empty($toolboxTalks)): ?>
    <p class="help-text"><?= t('user.toolbox_talks.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:12px;">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('user.toolbox_talks.topic') ?></th><th><?= t('user.toolbox_talks.attendee_count') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($toolboxTalks as $talk): ?>
        <tr>
          <td><?= e($talk['talk_date']) ?></td>
          <td>
            <?= e($talk['topic']) ?>
            <?php if ($talk['photo_path']): ?><br><a href="<?= e($talk['photo_path']) ?>" target="_blank"><?= t('user.punch_list.view_photo') ?></a><?php endif; ?>
          </td>
          <td><?= $talk['attendee_count'] !== null ? e((string)$talk['attendee_count']) : '—' ?></td>
          <td>
            <form method="post" action="/app/toolbox-talks/<?= $talk['id'] ?>/delete" onsubmit="return confirm('<?= t('user.toolbox_talks.remove_confirm') ?>');">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-sm btn-danger"><?= t('common.delete') ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
  <?php if ($toolboxTalkCount > count($toolboxTalks)): ?>
    <p style="margin:0 0 12px;"><a href="/app/projects/<?= $project['id'] ?>/toolbox-talks"><?= t('user.toolbox_talks.view_all', ['count' => $toolboxTalkCount]) ?></a></p>
  <?php endif; ?>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/toolbox-talks" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;width:160px;"><label><?= t('common.date') ?></label><input type="date" name="talk_date" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;"><label><?= t('user.toolbox_talks.topic') ?></label><input type="text" name="topic" placeholder="e.g. Ladder safety" required></div>
    <div class="form-group" style="margin:0;width:140px;"><label><?= t('user.toolbox_talks.attendee_count') ?></label><input type="number" min="0" name="attendee_count"></div>
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label><?= t('user.toolbox_talks.notes') ?></label><input type="text" name="notes"></div>
    <div class="form-group" style="margin:0;min-width:170px;"><label><?= t('user.punch_list.photo_optional') ?></label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.toolbox_talks.log_talk') ?></button>
  </form>
</div>

<script>
(function() {
  // Select+Other escape hatch for the safety incident type field — same convention as
  // Supplier's trade_category free-text field, but with suggested options via a select.
  document.querySelectorAll('.safety-incident-type-select').forEach(function(select) {
    var other = select.parentElement.querySelector('.safety-incident-type-other');
    if (!other) { return; }
    function sync() { other.style.display = select.value === 'other' ? 'block' : 'none'; }
    select.addEventListener('change', sync);
    sync();
  });
})();
</script>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.rfi.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.rfi.hint') ?></p>

  <?php if (empty($rfis)): ?>
    <p class="help-text"><?= t('user.rfi.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th>#</th><th><?= t('user.rfi.subject') ?></th><th><?= t('common.status') ?></th><th><?= t('user.rfi.due_date') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rfis as $r):
        $rfiStatusBadge = ['open' => 'yellow', 'answered' => 'blue', 'closed' => 'green'][$r['status']] ?? 'gray';
      ?>
        <tr>
          <td>RFI-<?= str_pad((string)$r['rfi_number'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($r['subject']) ?></td>
          <td><span class="badge badge-<?= $rfiStatusBadge ?>"><?= e($rfiStatuses[$r['status']] ?? ucfirst($r['status'])) ?></span></td>
          <td><?= e($r['due_date'] ?: '—') ?></td>
          <td><a href="/app/rfis/<?= $r['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($rfiCount > count($rfis)): ?>
      <a href="/app/projects/<?= $project['id'] ?>/rfis" class="btn btn-outline"><?= t('user.rfi.view_all', ['count' => $rfiCount]) ?></a>
    <?php endif; ?>
    <a href="/app/projects/<?= $project['id'] ?>/rfis/new" class="btn btn-primary"><?= t('user.rfi.new') ?></a>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.submittals.title') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.submittals.hint') ?></p>

  <?php if (empty($submittals)): ?>
    <p class="help-text"><?= t('user.submittals.none_yet') ?></p>
  <?php else: ?>
    <table class="data" style="margin-bottom:16px;">
      <thead><tr><th>#</th><th><?= t('common.title') ?></th><th><?= t('user.submittals.spec_section') ?></th><th><?= t('common.status') ?></th><th><?= t('user.submittals.due_date') ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($submittals as $s):
        $submittalStatusBadge = ['submitted' => 'gray', 'under_review' => 'yellow', 'approved' => 'green', 'approved_as_noted' => 'green', 'rejected' => 'red', 'revise_resubmit' => 'red'][$s['status']] ?? 'gray';
      ?>
        <tr>
          <td>SUB-<?= str_pad((string)$s['submittal_number'], 3, '0', STR_PAD_LEFT) ?></td>
          <td><?= e($s['title']) ?></td>
          <td><?= e($s['spec_section'] ?: '—') ?></td>
          <td><span class="badge badge-<?= $submittalStatusBadge ?>"><?= e($submittalStatuses[$s['status']] ?? ucfirst($s['status'])) ?></span></td>
          <td><?= e($s['due_date'] ?: '—') ?></td>
          <td><a href="/app/submittals/<?= $s['id'] ?>" class="btn btn-sm btn-light"><?= t('common.view') ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>

  <div style="display:flex;gap:8px;flex-wrap:wrap;">
    <?php if ($submittalCount > count($submittals)): ?>
      <a href="/app/projects/<?= $project['id'] ?>/submittals" class="btn btn-outline"><?= t('user.submittals.view_all', ['count' => $submittalCount]) ?></a>
    <?php endif; ?>
    <a href="/app/projects/<?= $project['id'] ?>/submittals/new" class="btn btn-primary"><?= t('user.submittals.new') ?></a>
  </div>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.site_photo_diary') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.site_photo_hint') ?></p>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/photos" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;" id="photo-upload-form">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('user.projects.photo') ?></label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.date') ?></label><input type="date" name="taken_on" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('user.projects.caption') ?></label><input type="text" name="caption" placeholder="e.g. Foundation poured, north wing"></div>
    <input type="hidden" name="latitude" id="photo-latitude">
    <input type="hidden" name="longitude" id="photo-longitude">
    <button type="submit" class="btn btn-outline"><?= t('user.projects.add_photo') ?></button>
  </form>
  <script>
  (function() {
    // Silently attaches the device GPS fix (when the browser/device grants it) to a photo
    // upload — never blocks the upload if geolocation is denied, unsupported, slow, or absent.
    var form = document.getElementById('photo-upload-form');
    if (!form || !('geolocation' in navigator)) { return; }
    form.addEventListener('submit', function(e) {
      if (form.dataset.geoAttempted === '1') { return; }
      e.preventDefault();
      form.dataset.geoAttempted = '1';
      var done = false;
      var proceed = function() { if (done) { return; } done = true; form.submit(); };
      var timeout = setTimeout(proceed, 4000);
      try {
        navigator.geolocation.getCurrentPosition(function(pos) {
          clearTimeout(timeout);
          document.getElementById('photo-latitude').value = pos.coords.latitude;
          document.getElementById('photo-longitude').value = pos.coords.longitude;
          proceed();
        }, function() { clearTimeout(timeout); proceed(); }, { timeout: 3500, maximumAge: 60000 });
      } catch (err) { clearTimeout(timeout); proceed(); }
    });
  })();
  </script>

  <?php if (empty($photos)): ?>
    <p class="help-text"><?= t('user.projects.no_photos_yet') ?></p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">
      <?php foreach ($photos as $photo): ?>
        <div>
          <a href="<?= e($photo['file_path']) ?>" target="_blank">
            <img src="<?= e($photo['file_path']) ?>" alt="<?= t('user.projects.site_photo_alt') ?>" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
          </a>
          <p class="help-text" style="margin-top:4px;margin-bottom:0;"><?= e($photo['taken_on'] ?: '') ?></p>
          <?php if ($photo['caption']): ?><p style="font-size:12.5px;margin:2px 0 4px;"><?= e($photo['caption']) ?></p><?php endif; ?>
          <?php if ($photo['latitude'] !== null && $photo['longitude'] !== null): ?>
            <p style="font-size:11.5px;margin:0 0 4px;"><a href="https://www.google.com/maps?q=<?= e((string)$photo['latitude']) ?>,<?= e((string)$photo['longitude']) ?>" target="_blank">📍 <?= e(number_format((float)$photo['latitude'], 4)) ?>, <?= e(number_format((float)$photo['longitude'], 4)) ?></a></p>
          <?php endif; ?>
          <form method="post" action="/app/project-photos/<?= $photo['id'] ?>/delete" onsubmit="return confirm('<?= t('user.projects.remove_photo_confirm') ?>');">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-light" style="width:100%;"><?= t('common.remove') ?></button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('common.schedule') ?></h3>
  <?php if (empty($tasks)): ?><p class="help-text"><?= t('user.projects.no_tasks_hint') ?> <a href="/app/schedule"><?= t('common.schedule') ?></a>.</p><?php else: ?>
    <table class="data"><thead><tr><th><?= t('user.dashboard.task_col') ?></th><th><?= t('common.start') ?></th><th><?= t('user.projects.end_col') ?></th><th><?= t('common.status') ?></th></tr></thead><tbody>
    <?php foreach ($tasks as $tk): ?>
      <tr><td><?= e($tk['title']) ?></td><td><?= e($tk['start_date']) ?></td><td><?= e($tk['end_date']) ?></td><td><span class="badge badge-gray"><?= e(str_replace('_',' ',$tk['status'])) ?></span></td></tr>
    <?php endforeach; ?>
    </tbody></table>
  <?php endif; ?>
</div>

@endsection
