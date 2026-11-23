@extends('layouts.app')

@section('content')
<div class="page-head">
  <h1><?= t('search.title') ?></h1>
</div>

<div class="toolbar" style="align-items:center;">
  <form method="get" action="/app/search" style="display:flex;gap:8px;flex:1;max-width:420px;">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= t('search.placeholder') ?>" style="flex:1;" autofocus>
    <button type="submit" class="btn btn-outline btn-sm"><?= t('common.search') ?></button>
  </form>
</div>

<?php if ($q === ''): ?>
  <div class="card empty-state">
    <div class="icon">🔎</div>
    <p><?= t('search.min_chars') ?></p>
  </div>
<?php elseif (mb_strlen($q) < $minLength): ?>
  <div class="card empty-state">
    <div class="icon">🔎</div>
    <p><?= t('search.min_chars') ?></p>
  </div>
<?php elseif ($total === 0): ?>
  <div class="card empty-state">
    <div class="icon">🔎</div>
    <h3><?= t('common.no_results') ?></h3>
  </div>
<?php else: ?>
  <p style="color:var(--muted);margin-top:-8px;"><?= t('search.results_for', ['query' => $q]) ?></p>
  <?php foreach ($groups as $group): ?>
    <?php if (empty($group['items'])) continue; ?>
    <div class="card" style="margin-bottom:16px;">
      <h3 style="margin-top:0;"><?= e($group['label']) ?></h3>
      <table class="data">
        <tbody>
        <?php foreach ($group['items'] as $item): ?>
          <tr><td><a href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
@endsection
