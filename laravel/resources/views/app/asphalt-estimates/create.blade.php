@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <h1>🛣️ <?= t('user.asphalt_estimates.new_title') ?></h1>
    <p class="help-text" style="margin-top:4px;"><?= t('user.asphalt_estimates.intro_hint') ?></p>
  </div>
  <a href="/app/asphalt-estimates" class="btn btn-light"><?= t('common.back') ?></a>
</div>

<?= view('app.asphalt-estimates._form', [
  'estimate' => null,
  'segments' => $segments,
  'projects' => $projects,
  'clients' => $clients,
  'taxRates' => $taxRates,
  'formAction' => $formAction,
  'submitLabel' => t('user.asphalt_estimates.create_button'),
])->render() ?>

@endsection
