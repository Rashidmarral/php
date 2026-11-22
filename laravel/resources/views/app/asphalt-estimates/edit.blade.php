@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>🛣️ <?= t('user.asphalt_estimates.edit_title') ?></h1>
  </div>
  <a href="/app/asphalt-estimates/<?= $estimate['id'] ?>" class="btn btn-light"><?= t('common.back') ?></a>
</div>

<?= view('app.asphalt-estimates._form', [
  'estimate' => $estimate,
  'segments' => $segments,
  'projects' => $projects,
  'clients' => $clients,
  'taxRates' => $taxRates,
  'formAction' => $formAction,
  'submitLabel' => t('common.save_changes'),
])->render() ?>

@endsection
