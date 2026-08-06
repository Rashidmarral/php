<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>New Takeoff</h1>
  <a href="/app/takeoffs" class="btn btn-light">← Back to takeoffs</a>
</div>

<form method="post" action="/app/takeoffs" enctype="multipart/form-data" class="card" style="max-width:600px;">
  <?= Csrf::field() ?>
  <div class="form-group">
    <label>Takeoff name</label>
    <input type="text" name="name" required placeholder="e.g. Villa Ground Floor Plan">
  </div>
  <div class="form-group">
    <label>Project (optional)</label>
    <select name="project_id">
      <option value="">— No project —</option>
      <?php foreach ($projects as $p): ?><option value="<?= $p['id'] ?>"><?= View::e($p['name']) ?></option><?php endforeach; ?>
    </select>
  </div>
  <div class="form-group">
    <label>Plan image (JPG, PNG, or WEBP)</label>
    <input type="file" name="plan_image" accept="image/png,image/jpeg,image/webp">
    <p class="help-text">Upload a floor plan or site drawing. You'll calibrate its scale on the next screen before measuring.</p>
  </div>
  <button type="submit" class="btn btn-primary">Create & open takeoff</button>
</form>
