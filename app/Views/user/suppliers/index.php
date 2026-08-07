<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Suppliers</h1>
  <a href="/app/suppliers/create" class="btn btn-primary">+ New Supplier</a>
</div>

<?php if (empty($suppliers)): ?>
  <div class="card empty-state">
    <div class="icon">🚚</div>
    <h3>No suppliers yet</h3>
    <p>Keep track of material and subcontractor suppliers, and link them to your pricing library.</p>
    <a href="/app/suppliers/create" class="btn btn-primary">+ New Supplier</a>
  </div>
<?php else: ?>
  <table class="data">
    <thead><tr><th>Name</th><th>Contact</th><th>Email</th><th>Phone</th><th>Category</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($suppliers as $s): ?>
      <tr>
        <td><?= View::e(View::local($s, 'name')) ?></td>
        <td><?= View::e($s['contact_name']) ?></td>
        <td><?= View::e($s['email']) ?></td>
        <td><?= View::e($s['phone']) ?></td>
        <td><?php if ($s['category']): ?><span class="badge badge-gray"><?= View::e($s['category']) ?></span><?php endif; ?></td>
        <td style="display:flex;gap:8px;">
          <a href="/app/suppliers/<?= $s['id'] ?>/edit" class="btn btn-sm btn-light">Edit</a>
          <form method="post" action="/app/suppliers/<?= $s['id'] ?>/delete" onsubmit="return confirm('Remove this supplier?');">
            <?= Csrf::field() ?>
            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
