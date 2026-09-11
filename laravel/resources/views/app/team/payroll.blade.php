@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <p class="help-text" style="margin-bottom:4px;"><a href="/app/team">&larr; <?= t('user.team.title') ?></a></p>
    <h1><?= e($member['name']) ?></h1>
  </div>
</div>

<p class="help-text" style="margin-top:-14px;margin-bottom:20px;">
  <?= t('user.team_payroll.hint') ?>
</p>

<div class="card" style="max-width:640px;">
  <form method="post" action="/app/team/<?= $member['id'] ?>/payroll">
    <?= csrf_field() ?>
    <h3 style="font-size:13px;margin-top:0;"><?= t('user.team_payroll.identity') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.team_payroll.national_id') ?></label><input type="text" name="national_id" value="<?= e($member['national_id'] ?? '') ?>" placeholder="1234567890"></div>
      <div class="form-group"><label><?= t('user.team_payroll.nationality') ?></label><input type="text" name="nationality" value="<?= e($member['nationality'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:13px;"><?= t('user.team_payroll.bank_details') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('common.iban') ?></label><input type="text" name="bank_iban" value="<?= e($member['bank_iban'] ?? '') ?>" placeholder="SA00 0000 0000 0000 0000 0000"></div>
      <div class="form-group"><label><?= t('user.team_payroll.bank_name') ?></label><input type="text" name="bank_name" value="<?= e($member['bank_name'] ?? '') ?>"></div>
    </div>

    <h3 style="font-size:13px;"><?= t('user.team_payroll.salary_breakdown') ?></h3>
    <div class="form-row">
      <div class="form-group"><label><?= t('user.team_payroll.basic_salary') ?></label><input type="number" step="0.01" min="0" name="basic_salary" value="<?= e($member['basic_salary'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('user.team_payroll.housing_allowance') ?></label><input type="number" step="0.01" min="0" name="housing_allowance" value="<?= e($member['housing_allowance'] ?? '') ?>"></div>
      <div class="form-group"><label><?= t('user.team_payroll.other_earnings') ?></label><input type="number" step="0.01" min="0" name="other_earnings" value="<?= e($member['other_earnings'] ?? '') ?>"></div>
    </div>
    <p class="help-text"><?= t('user.team_payroll.wps_hint') ?></p>

    <button type="submit" class="btn btn-primary"><?= t('user.team_payroll.save') ?></button>
  </form>
</div>

@endsection
