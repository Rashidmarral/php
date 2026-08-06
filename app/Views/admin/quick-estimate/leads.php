<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Quick Estimate Data</h1>
</div>

<div class="tabs">
  <a href="/admin/quick-estimate/regions">Regions</a>
  <a href="/admin/quick-estimate/foundations">Foundation Types</a>
  <a href="/admin/quick-estimate/addons">Add-ons</a>
  <a href="/admin/quick-estimate/leads" class="active">Leads</a>
</div>

<?php if (empty($leads)): ?>
  <div class="card empty-state">
    <div class="icon">🧮</div>
    <h3>No quick estimates submitted yet</h3>
    <p>Every visitor who generates an estimate from the public calculator will show up here.</p>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Date</th><th>Project</th><th>Contact</th><th>Region</th><th>Area</th><th>Total</th><th>Status</th></tr></thead>
    <tbody>
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
              <?php foreach (['new'=>'New','contacted'=>'Contacted','converted'=>'Converted','dismissed'=>'Dismissed'] as $val=>$label): ?>
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
