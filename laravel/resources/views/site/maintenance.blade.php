@extends('layouts.site')

@section('content')
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:600px;">
    <div class="card" style="padding:32px;text-align:center;">
      <div style="font-size:40px;">🛠️</div>
      <h1 style="margin-top:12px;"><?= t('maintenance.title') ?></h1>
      <p class="help-text" style="margin-top:8px;font-size:16px;"><?= e(app()->getLocale() === 'ar' ? $messageAr : $messageEn) ?></p>
      <p class="help-text" dir="<?= app()->getLocale() === 'ar' ? 'ltr' : 'rtl' ?>" style="margin-top:16px;opacity:.75;"><?= e(app()->getLocale() === 'ar' ? $messageEn : $messageAr) ?></p>
    </div>
  </div>
</section>
@endsection
