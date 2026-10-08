@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>⚡ <?= t('qe.title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.quick_estimate.intro_hint') ?></p>
  </div>
</div>

<?= view('app.quick-estimate._form', [
  'regions' => $regions,
  'foundations' => $foundations,
  'addons' => $addons,
  'qualityTiers' => $qualityTiers,
  'clients' => $clients,
  'vatRate' => $vatRate,
  'formAction' => '/app/quick-estimate',
  'submitLabel' => '✉ ' . t('qe.generate'),
])->render() ?>

<div class="card" style="margin-top:28px;">
  <h3><?= t('user.quick_estimate.my_quick_estimates') ?></h3>
  <?php if (empty($quotes)): ?>
    <p class="help-text"><?= t('user.quick_estimate.none_generated') ?></p>
  <?php else: ?>
    <table class="data">
      <thead><tr><th><?= t('common.date') ?></th><th><?= t('user.quick_estimate.project_col') ?></th><th><?= t('common.client') ?></th><th><?= t('user.quick_estimate.area_col') ?></th><th><?= t('common.total') ?></th><th></th></tr></thead>
      <tbody>
        <?php foreach ($quotes as $q): ?>
          <tr>
            <td><?= e($q['created_at']) ?></td>
            <td><a href="/app/quick-estimate/<?= $q['id'] ?>"><?= e($q['project_name'] ?: ('Quick Estimate #' . $q['id'])) ?></a></td>
            <td><?= e($q['client_name'] ?? '—') ?></td>
            <td><?= e($q['total_area']) ?> m²</td>
            <td><?= money((float)$q['total']) ?></td>
            <td style="display:flex;gap:6px;">
              <a href="/app/quick-estimate/<?= $q['id'] ?>" class="btn btn-outline btn-sm"><?= t('common.view') ?></a>
              <a href="/app/quick-estimate/<?= $q['id'] ?>/edit" class="btn btn-outline btn-sm"><?= t('common.edit') ?></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

@endsection
