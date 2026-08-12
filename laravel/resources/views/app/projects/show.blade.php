@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= e(local($project, 'name')) ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('common.client') ?>: <?= e($client ? local($client, 'name') : '—') ?></p>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/projects/<?= $project['id'] ?>/edit" class="btn btn-light"><?= t('common.edit') ?></a>
    <form method="post" action="/app/projects/<?= $project['id'] ?>/delete" onsubmit="return confirm('<?= t('user.projects.delete_confirm') ?>');">
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
  <h3>Budget vs. Actual</h3>
  <p class="help-text" style="margin-top:-6px;">Real costs recorded against vendor bills, compared to the revised budget (original + approved change orders).</p>

  <div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);margin-top:12px;">
    <div class="kpi"><div class="label">Revised budget</div><div class="value" style="font-size:20px;"><?= money($revisedBudget) ?></div></div>
    <div class="kpi"><div class="label">Actual spent</div><div class="value" style="font-size:20px;"><?= money($actualCostTotal) ?></div></div>
    <div class="kpi"><div class="label"><?= $budgetVariance >= 0 ? 'Remaining' : 'Over budget' ?></div><div class="value" style="font-size:20px;color:<?= $budgetVariance < 0 ? 'var(--danger)' : 'var(--brand-dark)' ?>;"><?= money(abs($budgetVariance)) ?></div></div>
  </div>
  <div style="background:var(--bg);border-radius:8px;height:10px;overflow:hidden;margin:14px 0 6px;">
    <div style="background:<?= $budgetUsedPercent > 100 ? 'var(--danger)' : 'linear-gradient(90deg,var(--brand),var(--brand-dark))' ?>;height:100%;width:<?= min(100, $budgetUsedPercent) ?>%;"></div>
  </div>
  <p class="help-text"><?= $budgetUsedPercent ?>% of revised budget spent</p>

  <?php if (!empty($vendorBills)): ?>
    <table class="data" style="margin:16px 0;">
      <thead><tr><th>Description</th><th>Category</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($vendorBills as $vb): ?>
        <tr>
          <td><?= e($vb['description']) ?><?php if ($vb['reference']): ?><br><span class="help-text"><?= e($vb['reference']) ?></span><?php endif; ?></td>
          <td><span class="badge badge-gray"><?= e(ucfirst($vb['category'])) ?></span></td>
          <td><?= e($vb['bill_date']) ?></td>
          <td><?= money((float)$vb['amount']) ?></td>
          <td><span class="badge badge-<?= $vb['status']==='paid'?'green':'yellow' ?>"><?= e(ucfirst($vb['status'])) ?></span></td>
          <td>
            <?php if ($vb['file_path']): ?><a href="<?= e($vb['file_path']) ?>" target="_blank" class="btn btn-sm btn-light">📎</a><?php endif; ?>
            <form method="post" action="/app/vendor-bills/<?= $vb['id'] ?>/delete" onsubmit="return confirm('Remove this vendor bill?');" style="display:inline;">
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
    <div class="form-group" style="margin:0;flex:2;min-width:200px;"><label>Description</label><input type="text" name="description" placeholder="e.g. Rebar delivery — invoice #4521" required></div>
    <div class="form-group" style="margin:0;width:140px;"><label>Category</label>
      <select name="category">
        <?php foreach (\App\Models\VendorBill::CATEGORIES as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:150px;"><label>Supplier</label>
      <select name="supplier_id">
        <option value="">—</option>
        <?php foreach ($suppliers as $s): ?><option value="<?= $s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;width:130px;"><label>Amount (SAR)</label><input type="number" step="0.01" min="0.01" name="amount" required></div>
    <div class="form-group" style="margin:0;width:150px;"><label>Bill date</label><input type="date" name="bill_date"></div>
    <div class="form-group" style="margin:0;width:150px;"><label>Reference #</label><input type="text" name="reference" placeholder="Invoice / PO #"></div>
    <div class="form-group" style="margin:0;min-width:180px;"><label>Receipt (optional)</label><input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png"></div>
    <button type="submit" class="btn btn-outline">Add vendor bill</button>
  </form>
</div>

<div class="card" style="margin-top:24px;">
  <h3><?= t('user.projects.site_photo_diary') ?></h3>
  <p class="help-text" style="margin-top:-6px;"><?= t('user.projects.site_photo_hint') ?></p>

  <form method="post" action="/app/projects/<?= $project['id'] ?>/photos" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:end;flex-wrap:wrap;margin-bottom:16px;">
    <?= csrf_field() ?>
    <div class="form-group" style="margin:0;"><label><?= t('user.projects.photo') ?></label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required></div>
    <div class="form-group" style="margin:0;"><label><?= t('common.date') ?></label><input type="date" name="taken_on" value="<?= date('Y-m-d') ?>"></div>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label><?= t('user.projects.caption') ?></label><input type="text" name="caption" placeholder="e.g. Foundation poured, north wing"></div>
    <button type="submit" class="btn btn-outline"><?= t('user.projects.add_photo') ?></button>
  </form>

  <?php if (empty($photos)): ?>
    <p class="help-text"><?= t('user.projects.no_photos_yet') ?></p>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:14px;">
      <?php foreach ($photos as $photo): ?>
        <div>
          <a href="<?= e($photo['file_path']) ?>" target="_blank">
            <img src="<?= e($photo['file_path']) ?>" alt="Site photo" style="width:100%;height:120px;object-fit:cover;border-radius:8px;border:1px solid var(--border);">
          </a>
          <p class="help-text" style="margin-top:4px;margin-bottom:0;"><?= e($photo['taken_on'] ?: '') ?></p>
          <?php if ($photo['caption']): ?><p style="font-size:12.5px;margin:2px 0 4px;"><?= e($photo['caption']) ?></p><?php endif; ?>
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
