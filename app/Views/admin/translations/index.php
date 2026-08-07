<?php use App\Core\View; use App\Core\Csrf; ?>
<div class="page-head">
  <h1>Translations</h1>
</div>
<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  Edit any text shown on the public website, in emails, or in the app — in English and Arabic.
  Overridden strings are marked <span class="badge badge-blue">Custom</span>; use Reset to go back to the built-in wording.
  <?= $total ?> keys total.
</p>

<form method="get" action="/admin/translations" class="toolbar">
  <input type="text" name="q" value="<?= View::e($search) ?>" placeholder="Search by key or text…" style="max-width:320px;">
  <button type="submit" class="btn btn-light">Search</button>
  <?php if ($search !== ''): ?><a href="/admin/translations" class="btn btn-light">Clear</a><?php endif; ?>
</form>

<div class="card" style="margin-bottom:20px;">
  <h3 style="font-size:14px;">Add a new key</h3>
  <p class="help-text" style="margin-top:-6px;">Use this to add copy for a spot you've referenced with <code>t('your.key')</code> in a custom template.</p>
  <form method="post" action="/admin/translations/store" style="display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
    <?= Csrf::field() ?>
    <div class="form-group" style="margin:0;flex:1;min-width:180px;"><label>Key</label><input type="text" name="new_key" placeholder="e.g. custom.banner.title" required></div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;"><label>English value</label><input type="text" name="new_en_value"></div>
    <div class="form-group" style="margin:0;flex:1;min-width:200px;"><label>Arabic value</label><input type="text" name="new_ar_value" dir="rtl"></div>
    <button type="submit" class="btn btn-outline">+ Add key</button>
  </form>
</div>

<?php if (empty($rows)): ?>
  <div class="empty-state card"><p>No translation keys match "<?= View::e($search) ?>".</p></div>
<?php else: ?>
  <table class="data">
    <thead><tr><th style="width:22%;">Key</th><th>English</th><th>Arabic</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $fid = 'tr-' . md5($r['key']); ?>
      <form id="<?= $fid ?>" method="post" action="/admin/translations/update">
        <?= Csrf::field() ?>
        <input type="hidden" name="key" value="<?= View::e($r['key']) ?>">
        <input type="hidden" name="q" value="<?= View::e($search) ?>">
      </form>
      <tr>
        <td>
          <code style="font-size:12px;"><?= View::e($r['key']) ?></code>
          <?php if ($r['is_custom']): ?><br><span class="badge badge-blue" style="margin-top:4px;">Custom</span><?php endif; ?>
        </td>
        <td><input form="<?= $fid ?>" type="text" name="en_value" value="<?= View::e($r['en_value']) ?>" style="min-width:220px;" title="Default: <?= View::e($r['en_default']) ?>"></td>
        <td><input form="<?= $fid ?>" type="text" name="ar_value" value="<?= View::e($r['ar_value']) ?>" dir="rtl" style="min-width:220px;" title="Default: <?= View::e($r['ar_default']) ?>"></td>
        <td style="display:flex;gap:6px;">
          <button form="<?= $fid ?>" type="submit" class="btn btn-sm btn-light">Save</button>
          <?php if ($r['is_overridden']): ?>
            <form method="post" action="/admin/translations/reset" style="display:inline;" onsubmit="return confirm('Reset this key to its default wording?');">
              <?= Csrf::field() ?>
              <input type="hidden" name="key" value="<?= View::e($r['key']) ?>">
              <input type="hidden" name="q" value="<?= View::e($search) ?>">
              <button type="submit" class="btn btn-sm btn-danger">Reset</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
