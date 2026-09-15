@extends('layouts.app')

@section('content')
<div class="page-head">
  <div>
    <a href="/app/zakat" class="help-text"><?= t('user.zakat.back_to_list') ?></a>
    <h1 style="margin-top:6px;">🕌 <?= t('user.zakat.estimate_for', ['date' => $calculation->period_end_date->format('Y-m-d')]) ?></h1>
  </div>
  <div style="display:flex;gap:8px;">
    <a href="/app/zakat/<?= $calculation->id ?>/pdf" target="_blank" class="btn btn-outline">⬇ <?= t('user.zakat.download_pdf') ?></a>
    <form method="post" action="/app/zakat/<?= $calculation->id ?>/delete" onsubmit="return confirm('<?= t('user.zakat.delete_confirm') ?>');">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-danger"><?= t('common.delete') ?></button>
    </form>
  </div>
</div>

<div class="alert" style="background:#fdf3e0;color:var(--warning);border:1px solid #e8c76b;">
  ⚠️ <?= t('user.zakat.disclaimer') ?>
</div>

<div class="card" style="max-width:720px;">
  <table class="data">
    <tbody>
      <tr><td><?= t('user.zakat.period_end_date') ?></td><td><?= e($calculation->period_end_date->format('Y-m-d')) ?></td></tr>
      <tr><td><?= t('user.zakat.rate_type') ?></td><td><?= t('user.zakat.rate_' . $calculation->rate_type) ?></td></tr>
      <tr><td><?= t('user.zakat.equity_amount') ?></td><td><?= money((float) $calculation->equity_amount) ?></td></tr>
      <tr><td><?= t('user.zakat.long_term_liabilities') ?></td><td><?= money((float) $calculation->long_term_liabilities) ?></td></tr>
      <tr><td><?= t('user.zakat.net_fixed_assets') ?></td><td>-<?= money((float) $calculation->net_fixed_assets) ?></td></tr>
      <tr><td><?= t('user.zakat.other_deductions') ?></td><td>-<?= money((float) $calculation->other_deductions) ?></td></tr>
      <tr><td><strong><?= t('user.zakat.zakat_base') ?></strong></td><td><strong><?= money((float) $calculation->zakat_base) ?></strong></td></tr>
      <tr><td><?= t('user.zakat.rate_applied') ?></td><td><?= $calculation->rate_type === 'gregorian' ? '2.5775%' : '2.5%' ?></td></tr>
      <tr><td><strong><?= t('user.zakat.zakat_due') ?></strong></td><td><strong style="font-size:18px;color:var(--brand-dark);"><?= money((float) $calculation->zakat_due) ?></strong></td></tr>
      <?php if ($calculation->notes): ?>
        <tr><td><?= t('common.notes') ?></td><td><?= nl2br(e($calculation->notes)) ?></td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

@endsection
