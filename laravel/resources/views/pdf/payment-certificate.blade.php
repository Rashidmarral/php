<?php
/** @var \App\Models\Company|null $company */
/** @var \App\Models\Project $project */
/** @var \App\Models\Client|null $client */
/** @var \App\Models\PaymentCertificate $certificate */
$rtl = $lang === 'ar';
$T = fn ($v) => pdfText($v, $lang);
?><!doctype html>
<html lang="<?= $lang ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
@page { margin: 18mm 15mm; }
* { box-sizing: border-box; }
body {
  font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif;
  color: #16211f;
  font-size: 11.5px;
  direction: <?= $rtl ? 'rtl' : 'ltr' ?>;
}
table { width: 100%; border-collapse: collapse; }
.head-table td { vertical-align: top; padding: 0; }
.doc-title { font-size: 20px; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; color: #0a4d42; }
.doc-number { font-size: 13px; color: #555; margin-top: 4px; }
.company-name { font-size: 16px; font-weight: 700; }
.meta-line { font-size: 10.5px; color: #555; margin-top: 2px; }
.section-title { font-size: 10px; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 4px; }
.party-name { font-size: 12.5px; font-weight: 700; }
.status-badge { display: inline-block; padding: 3px 12px; border-radius: 3px; font-size: 10px; font-weight: 700; text-transform: uppercase; background: #e6f4f1; color: #0a4d42; }
.items-table { margin-top: 16px; }
.items-table th { font-size: 9.5px; text-transform: uppercase; letter-spacing: .02em; padding: 6px 8px; text-align: <?= $rtl ? 'right' : 'left' ?>; background: #e6f4f1; color: #0a4d42; }
.items-table td { padding: 6px 8px; font-size: 10px; border-bottom: 1px solid #e6ecea; }
.items-table .num { text-align: <?= $rtl ? 'left' : 'right' ?>; }
.section-row td { background: var(--bg, #f2f5f4); font-weight: 700; background: #f2f5f4; }
.totals-table { width: 300px; <?= $rtl ? 'float:right;' : 'float:left;' ?> margin-top: 16px; }
.totals-table td { padding: 5px 10px; font-size: 11px; }
.totals-table .num { text-align: <?= $rtl ? 'left' : 'right' ?>; }
.totals-table .grand { font-size: 14px; font-weight: 700; border-top: 2px solid #0f6e5f; color: #0a4d42; }
.progress-table { width: 260px; <?= $rtl ? 'float:left;' : 'float:right;' ?> margin-top: 16px; }
.progress-table td { padding: 5px 10px; font-size: 10.5px; }
.progress-table .num { text-align: <?= $rtl ? 'left' : 'right' ?>; }
.notes-box { clear: both; margin-top: 40px; padding-top: 10px; font-size: 10px; color: #666; border-top: 1px solid #e6ecea; }
.footer-note { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 9px; color: #999; }
</style>
</head>
<body>

<table class="head-table"><tr>
  <td style="width:60%">
    <div class="company-name"><?= $T($company->name ?? '') ?></div>
    <?php if (!empty($company->name_ar)): ?><div class="meta-line"><?= pdfText($company->name_ar, 'ar') ?></div><?php endif; ?>
    <?php if (!empty($company->vat_number)): ?><div class="meta-line">VAT: <?= $T($company->vat_number) ?></div><?php endif; ?>
    <?php if (!empty($company->cr_number)): ?><div class="meta-line">CR: <?= $T($company->cr_number) ?></div><?php endif; ?>
  </td>
  <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
    <div class="doc-title"><?= $rtl ? 'مستخلص دفعة' : 'Payment Certificate' ?></div>
    <div class="doc-number">#<?= $certificate->certificate_number ?></div>
    <div class="meta-line"><?= $T((string) $certificate->certificate_date) ?></div>
    <div style="margin-top:6px;"><span class="status-badge"><?= $T($certificate->status) ?></span></div>
  </td>
</tr></table>

<table class="head-table" style="margin-top:16px;"><tr>
  <td style="width:50%">
    <div class="section-title"><?= $rtl ? 'المشروع' : 'Project' ?></div>
    <div class="party-name"><?= $T(local($project->toArray(), 'name')) ?></div>
    <?php if ($client): ?>
      <div class="section-title" style="margin-top:8px;"><?= $rtl ? 'العميل' : 'Client' ?></div>
      <div class="party-name"><?= $T(local($client->toArray(), 'name')) ?></div>
    <?php endif; ?>
  </td>
  <td style="width:50%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
    <?php if ($certificate->period_from || $certificate->period_to): ?>
      <div class="meta-line"><?= $rtl ? 'الفترة' : 'Period' ?>: <?= $T((string) $certificate->period_from) ?> &ndash; <?= $T((string) $certificate->period_to) ?></div>
    <?php endif; ?>
  </td>
</tr></table>

<table class="items-table">
  <thead>
    <tr>
      <th><?= $rtl ? 'البيان' : 'Description' ?></th>
      <th><?= $rtl ? 'الوحدة' : 'UOM' ?></th>
      <th class="num"><?= $rtl ? 'الكمية التعاقدية' : 'Contract Qty' ?></th>
      <th class="num"><?= $rtl ? 'سعر الوحدة' : 'Rate' ?></th>
      <th class="num"><?= $rtl ? 'السابق تراكمي' : 'Prev. Cumulative' ?></th>
      <th class="num"><?= $rtl ? 'التراكمي الحالي' : 'This Cumulative' ?></th>
      <th class="num"><?= $rtl ? 'كمية الفترة' : 'Period Qty' ?></th>
      <th class="num"><?= $rtl ? 'قيمة الفترة' : 'Period Value' ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($lines as $line): ?>
      <tr>
        <td><?= $T($line->description) ?></td>
        <td><?= $T($line->uom) ?></td>
        <td class="num"><?= number_format((float) $line->contract_qty, 2) ?></td>
        <td class="num"><?= number_format((float) $line->contract_unit_price, 2) ?></td>
        <td class="num"><?= number_format((float) $line->previous_cumulative_qty, 2) ?></td>
        <td class="num"><?= number_format((float) $line->cumulative_qty, 2) ?></td>
        <td class="num"><?= number_format((float) $line->this_period_qty, 2) ?></td>
        <td class="num"><?= number_format((float) $line->this_period_value, 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="progress-table">
  <tr><td><?= $rtl ? 'قيمة العقد الإجمالية' : 'Total Contract Value' ?></td><td class="num"><?= number_format($contractValue, 2) ?> <?= $currency ?></td></tr>
  <tr><td><?= $rtl ? 'المعتمد تراكميًا' : 'Cumulative Certified' ?></td><td class="num"><?= number_format((float) $certificate->cumulative_certified, 2) ?> <?= $currency ?></td></tr>
  <tr><td><?= $rtl ? 'النسبة المكتملة' : '% Complete' ?></td><td class="num"><?= $contractValue > 0 ? number_format((float) $certificate->cumulative_certified / $contractValue * 100, 1) : '0.0' ?>%</td></tr>
</table>

<table class="totals-table">
  <tr><td><?= $rtl ? 'المطالبة الإجمالية' : 'Gross Claim (This Period)' ?></td><td class="num"><?= number_format((float) $certificate->gross_amount, 2) ?> <?= $currency ?></td></tr>
  <tr><td><?= $rtl ? 'خصم الاحتجاز' : 'Less: Retention' ?> (<?= (float) $certificate->retention_percent ?>%)</td><td class="num">-<?= number_format((float) $certificate->retention_amount, 2) ?> <?= $currency ?></td></tr>
  <?php if ((float) $certificate->advance_recovery_amount > 0): ?>
    <tr><td><?= $rtl ? 'خصم استرداد الدفعة المقدمة' : 'Less: Advance Recovery' ?></td><td class="num">-<?= number_format((float) $certificate->advance_recovery_amount, 2) ?> <?= $currency ?></td></tr>
  <?php endif; ?>
  <tr><td style="font-weight:700;"><?= $rtl ? 'صافي المستحق' : 'Net Payable' ?></td><td class="num" style="font-weight:700;"><?= number_format((float) $certificate->net_payable, 2) ?> <?= $currency ?></td></tr>
  <tr><td><?= $rtl ? 'ضريبة القيمة المضافة' : 'VAT' ?> (<?= $vatRate ?>%)</td><td class="num"><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
  <tr class="grand"><td><?= $rtl ? 'الإجمالي المستحق' : 'Total Due' ?></td><td class="num"><?= number_format($totalDue, 2) ?> <?= $currency ?></td></tr>
</table>
<div style="clear:both;"></div>

<?php if (!empty($certificate->notes)): ?>
  <div class="notes-box">
    <div class="section-title"><?= $rtl ? 'ملاحظات' : 'Notes' ?></div>
    <div><?= $T($certificate->notes) ?></div>
  </div>
<?php endif; ?>

<div class="footer-note"><?= $rtl ? 'تم إنشاؤه بواسطة ' . \App\Models\Setting::siteName() : 'Generated by ' . \App\Models\Setting::siteName() ?></div>

</body>
</html>
