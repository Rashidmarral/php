@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1><?= t('user.quick_estimate.edit_title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= e($estimate['project_name'] ?: ('Quick Estimate #' . $estimate['id'])) ?></p>
  </div>
  <a href="/app/quick-estimate/<?= $estimate['id'] ?>" class="btn btn-light"><?= t('common.back') ?></a>
</div>

<?= view('app.quick-estimate._form', [
  'regions' => $regions,
  'foundations' => $foundations,
  'addons' => $addons,
  'qualityTiers' => $qualityTiers,
  'clients' => $clients,
  'vatRate' => $vatRate,
  'formAction' => '/app/quick-estimate/' . $estimate['id'] . '/update',
  'submitLabel' => '💾 ' . t('common.save_changes'),
  'projectName' => $estimate['project_name'],
  'selectedClientId' => $estimate['client_id'],
  'selectedRegionId' => $estimate['region_id'],
  'selectedFoundationId' => $estimate['foundation_id'],
  'selectedTierId' => $estimate['quality_tier_id'],
  'totalArea' => $estimate['total_area'],
  'discountPercent' => $estimate['discount_percent'],
  'selectedAddonIds' => $selectedAddonIds,
  'addonQty' => $addonQty,
])->render() ?>

@endsection
