<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1><?= t('admin.qe.title') ?></h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions"><?= t('admin.qe.tab_regions') ?></a>
  <a href="/admin/quick-estimate/foundations"><?= t('admin.qe.tab_foundations') ?></a>
  <a href="/admin/quick-estimate/addons"><?= t('admin.qe.tab_addons') ?></a>
  <a href="/admin/quick-estimate/leads" class="active"><?= t('admin.qe.tab_leads') ?></a>
</div>

<?php if (empty($leads)): ?>
  <div class="card empty-state">
    <div class="icon">🧮</div>
    <h3><?= t('admin.qe.no_leads') ?></h3>
    <p><?= t('admin.qe.no_leads_hint') ?></p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th><?= t('common.date') ?></th><th><?= t('admin.qe.project') ?></th><th><?= t('common.contact') ?></th><th><?= t('admin.qe.region') ?></th><th><?= t('admin.qe.area') ?></th><th><?= t('common.total') ?></th><th><?= t('common.status') ?></th></tr></thead>
    <tbody>
    <?php $statusLabels = ['new' => t('admin.qe.lead_status_new'), 'contacted' => t('admin.qe.lead_status_contacted'), 'converted' => t('admin.qe.lead_status_converted'), 'dismissed' => t('admin.qe.lead_status_dismissed')]; ?>
    <?php foreach ($leads as $lead): ?>
      <tr>
        <td class="help-text"><?= View::e($lead['created_at']) ?></td>
        <td><?= View::e($lead['project_name'] ?: '—') ?></td>
        <td>
          <?= View::e($lead['contact_name'] ?: '—') ?>
          <?php if ($lead['contact_email']): ?><div class="help-text"><?= View::e($lead['contact_email']) ?></div><?php endif; ?>
          <?php if ($lead['contact_phone']): ?><div class="help-text"><?= View::e($lead['contact_phone']) ?></div><?php endif; ?>
        </td>
        <td><?= View::e($lead['region_name'] ?? '—') ?></td>
        <td><?= View::e((string)$lead['total_area']) ?> m²</td>
        <td><?= View::money((float)$lead['total']) ?></td>
        <td>
          <form method="post" action="/admin/quick-estimate/leads/<?= $lead['id'] ?>/status">
            <?= Csrf::field() ?>
            <select name="status" onchange="this.form.submit()" class="badge badge-<?= ['converted'=>'green','contacted'=>'blue','dismissed'=>'gray'][$lead['status']] ?? 'yellow' ?>" style="border:none;padding:4px 8px;">
              <?php foreach ($statusLabels as $val=>$label): ?>
                <option value="<?= $val ?>" <?= $lead['status']===$val?'selected':'' ?>><?= $label ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
