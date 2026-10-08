<?php
/** @var \App\Models\Company|null $company */
/** @var \App\Models\Project $project */
/** @var \App\Models\Client|null $client */
/** @var \App\Models\PaymentCertificate $certificate */
$rtl = $lang === 'ar';
$T = fn ($v) => pdfText($v, $lang);
$Tboth = fn ($v) => pdfText($v, 'ar');
// Same template-whitelist pattern as InvoiceController::pdf(), NOW including 'saudi': a
// Payment Certificate is a genuine Saudi construction document (an IPC/مستخلص) in its own
// right, so it gets the same bilingual bordered ZATCA-style layout as document.blade.php's
// tax-invoice/quotation template — see the `$tpl === 'saudi'` branch below for how its wider
// progress/retention columns and non-tax-invoice title/signature wording were adapted rather
// than force-fit onto document.blade.php's simpler 5-column shape. The other 5 remain plain
// look-and-feel (color/border/font) variants, applied here unchanged via PdfTemplateStyles.
$tpl = in_array($template ?? null, ['classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $template : 'modern';
?><!doctype html>
<html lang="<?= $lang ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
@page { margin: 20mm 16mm; }
* { box-sizing: border-box; }
body {
  /* Cairo (Latin + Arabic) is the default; DejaVu Sans/Noto Naskh Arabic stay as
     documented fallbacks in case an edge-case glyph is ever missing from Cairo. */
  font-family: 'Cairo', <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif;
  color: #16211f;
  font-size: 11.5px;
  direction: <?= $rtl ? 'rtl' : 'ltr' ?>;
}
table { width: 100%; border-collapse: collapse; }
<?= \App\Support\Pdf\PdfTemplateStyles::baseChrome() ?>
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
<?= \App\Support\Pdf\PdfTemplateStyles::statusAndFooterChrome() ?>

<?= \App\Support\Pdf\PdfTemplateStyles::variants($rtl) ?>

<?= \App\Support\Pdf\PdfTemplateStyles::saudiChrome($rtl) ?>
/* ---- Saudi template: Payment Certificate-only additions ----
   The shared .saudi-items-table already uses compact 9.5-10px fonts for document.blade.php's
   5-column invoice/quotation table; this certificate's table needs 8 columns (contract qty,
   rate, previous/this cumulative, period qty/value), so `.dense` shrinks those fonts further
   to keep the whole table on one A4 page without dropping any column. */
.tpl-saudi .saudi-items-table.dense th { font-size: 7.5px; padding: 4px 3px; line-height: 1.3; }
.tpl-saudi .saudi-items-table.dense td { font-size: 8.5px; padding: 4px 3px; }
</style>
</head>
<body class="tpl-<?= $tpl ?>">

<?php if ($tpl === 'saudi'): ?>
  <?php
    // The "build it properly" bilingual Saudi layout for a Payment Certificate — same visual
    // language as document.blade.php's saudi branch (bordered header, centered title bar,
    // bilingual info/items/totals, amount-in-words, signature block), adapted in two places:
    //  1. Title/labels: a Payment Certificate is not a ZATCA tax invoice or quotation, so it
    //     does not get "TAX INVOICE"/"QUOTATION" wording. It gets "PAYMENT CERTIFICATE" /
    //     "مستخلص دفعة" — the exact Arabic term this same file already used in its head-band
    //     title before this branch existed (see the non-saudi branch below). All info/totals
    //     labels below reuse this file's own existing English/Arabic pairs verbatim (Project/
    //     المشروع, Client/العميل, Gross Claim/المطالبة الإجمالية, etc.) rather than inventing
    //     new wording.
    //  2. Items table: a certificate's line is cumulative-billing data (contract qty/rate,
    //     previous/this cumulative, period qty/value), not a simple qty/unit-price/total
    //     invoice line, so it's a genuinely wider 8-column table instead of document.blade.php's
    //     5-column one. It reuses the exact same `.saudi-items-table` classes/borders from the
    //     shared PdfTemplateStyles::saudiChrome() CSS, with a local `.dense` modifier (see the
    //     <style> block above) to shrink the fonts enough to still fit one A4 page — no column
    //     is dropped to force a narrower table.
    //
    //  Signature block / amount-in-words judgment call: a Payment Certificate is an internal
    //  claim-approval document between contractor and client/consultant, not a ZATCA tax
    //  instrument, so "Recipient"/"Seller" (the invoice's buyer/seller signing roles) don't fit.
    //  It keeps a signature block, relabeled "Certified by" / "Approved by" — which matches how
    //  an IPC is actually signed off in practice (prepared/certified by the contractor's QS,
    //  approved by the consultant or client) — and keeps the amount-in-words line, since
    //  spelling out the certified total unambiguously is exactly as useful on a claim document
    //  as it is on an invoice, and this file already treats totalDue as the certificate's
    //  headline payable figure.
    $companyLogo = !empty($company?->logo_path) ? ('file://' . public_path($company->logo_path)) : null;
    $projectNameEn = $project->name ?? '';
    $projectNameAr = $project->name_ar ?? '';
    $clientNameEn = $client->name ?? '';
    $clientNameAr = $client->name_ar ?? '';
  ?>
  <table class="saudi-header"><tr>
    <td style="width:35%">
      <div class="saudi-company-en"><?= pdfText($company->name ?? '', 'en') ?></div>
      <?php if (!empty($company->vat_number)): ?><div class="saudi-meta-en">VAT: <?= $T($company->vat_number) ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="saudi-meta-en">CR: <?= $T($company->cr_number) ?></div><?php endif; ?>
    </td>
    <td class="saudi-logo" style="width:30%">
      <?php if ($companyLogo): ?><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"><?php endif; ?>
    </td>
    <td style="width:35%">
      <div class="saudi-company-ar"><?= !empty($company->name_ar) ? $Tboth($company->name_ar) : e($company->name ?? '') ?></div>
      <?php if (!empty($company->vat_number)): ?><div class="saudi-meta-ar"><?= $Tboth('الرقم الضريبي') ?>: <?= e($company->vat_number) ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="saudi-meta-ar"><?= $Tboth('السجل التجاري') ?>: <?= e($company->cr_number) ?></div><?php endif; ?>
    </td>
  </tr></table>

  <div class="saudi-title-bar">PAYMENT CERTIFICATE &nbsp;|&nbsp; <?= $Tboth('مستخلص دفعة') ?></div>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:33%">Certificate No: <?= $T((string) $certificate->certificate_number) ?></td>
    <td class="en" style="width:34%">Status: <?= $T(ucfirst($certificate->status)) ?></td>
    <td class="ar" style="width:33%"><?= $Tboth('التاريخ') ?>: <?= $T((string) $certificate->certificate_date) ?></td>
  </tr></table>

  <?php if ($certificate->period_from || $certificate->period_to): ?>
  <table class="saudi-info-table"><tr>
    <td class="en" style="width:50%">Period: <?= $T((string) $certificate->period_from) ?> &ndash; <?= $T((string) $certificate->period_to) ?></td>
    <td class="ar" style="width:50%"><?= $Tboth('الفترة') ?>: <?= $T((string) $certificate->period_from) ?> &ndash; <?= $T((string) $certificate->period_to) ?></td>
  </tr></table>
  <?php endif; ?>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:50%">
      Project: <?= pdfText($projectNameEn, 'en') ?>
      <?php if ($client): ?><br>Client: <?= pdfText($clientNameEn, 'en') ?><?php endif; ?>
    </td>
    <td class="ar" style="width:50%">
      <?= $Tboth('المشروع') ?>: <?= $projectNameAr !== '' ? $Tboth($projectNameAr) : e($projectNameEn) ?>
      <?php if ($client): ?><br><?= $Tboth('العميل') ?>: <?= $clientNameAr !== '' ? $Tboth($clientNameAr) : e($clientNameEn) ?><?php endif; ?>
    </td>
  </tr></table>

  <table class="saudi-items-table dense">
    <thead>
      <tr>
        <th style="width:20%">Description<br><?= $Tboth('البيان') ?></th>
        <th style="width:7%">UOM<br><?= $Tboth('الوحدة') ?></th>
        <th style="width:10%">Contract Qty<br><?= $Tboth('الكمية التعاقدية') ?></th>
        <th style="width:11%">Rate<br><?= $Tboth('سعر الوحدة') ?></th>
        <th style="width:12%">Prev. Cumulative<br><?= $Tboth('السابق تراكمي') ?></th>
        <th style="width:12%">This Cumulative<br><?= $Tboth('التراكمي الحالي') ?></th>
        <th style="width:11%">Period Qty<br><?= $Tboth('كمية الفترة') ?></th>
        <th style="width:17%">Period Value<br><?= $Tboth('قيمة الفترة') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $line): ?>
        <tr>
          <td class="desc"><?= pdfText($line->description, 'en') ?></td>
          <td><?= $T($line->uom) ?></td>
          <td><?= number_format((float) $line->contract_qty, 2) ?></td>
          <td><?= number_format((float) $line->contract_unit_price, 2) ?></td>
          <td><?= number_format((float) $line->previous_cumulative_qty, 2) ?></td>
          <td><?= number_format((float) $line->cumulative_qty, 2) ?></td>
          <td><?= number_format((float) $line->this_period_qty, 2) ?></td>
          <td><?= number_format((float) $line->this_period_value, 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="saudi-totals-table" style="width:300px;<?= $rtl ? 'float:left;' : 'float:right;' ?>">
    <tr><td class="label">Contract Value / <?= $Tboth('قيمة العقد الإجمالية') ?></td><td><?= number_format($contractValue, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">Cumulative Certified / <?= $Tboth('المعتمد تراكميًا') ?></td><td><?= number_format((float) $certificate->cumulative_certified, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">% Complete / <?= $Tboth('النسبة المكتملة') ?></td><td><?= $contractValue > 0 ? number_format((float) $certificate->cumulative_certified / $contractValue * 100, 1) : '0.0' ?>%</td></tr>
  </table>

  <table class="saudi-totals-table" style="width:320px;<?= $rtl ? 'float:right;' : 'float:left;' ?>">
    <tr><td class="label">Gross Claim / <?= $Tboth('المطالبة الإجمالية') ?></td><td><?= number_format((float) $certificate->gross_amount, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">Retention (<?= (float) $certificate->retention_percent ?>%) / <?= $Tboth('خصم الاحتجاز') ?></td><td>-<?= number_format((float) $certificate->retention_amount, 2) ?> <?= $currency ?></td></tr>
    <?php if ((float) $certificate->advance_recovery_amount > 0): ?>
      <tr><td class="label">Advance Recovery / <?= $Tboth('خصم استرداد الدفعة المقدمة') ?></td><td>-<?= number_format((float) $certificate->advance_recovery_amount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <tr><td class="label">Net Payable / <?= $Tboth('صافي المستحق') ?></td><td><?= number_format((float) $certificate->net_payable, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">VAT <?= $vatRate ?>% / <?= $Tboth('ضريبة القيمة المضافة') ?></td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
    <tr class="grand"><td class="label">Total Due / <?= $Tboth('الإجمالي المستحق') ?></td><td><?= number_format($totalDue, 2) ?> <?= $currency ?></td></tr>
  </table>
  <div style="clear:both;"></div>

  <div class="saudi-words">Amount in words: <?= \App\Support\Pdf\NumberToWords::sar($totalDue) ?></div>

  <?php if (!empty($certificate->notes)): ?>
    <!-- The shared .notes-box class carries a 60px top margin sized for the roomier non-saudi
         templates; the saudi layout is deliberately tight (bordered blocks, small fonts), so
         notes get their own compact inline block here instead — same reason document.blade.php's
         own saudi branch skips .notes-box entirely for invoices' $notes field. -->
    <div style="clear:both;margin-top:8px;font-size:9.5px;color:#444;">
      <strong>Notes / <?= $Tboth('ملاحظات') ?>:</strong> <?= pdfText($certificate->notes, 'en') ?>
    </div>
  <?php endif; ?>

  <table class="saudi-sign-table"><tr>
    <td style="width:50%">Certified by / <?= $Tboth('معتمد من') ?>: ______________________</td>
    <td style="width:50%">Approved by / <?= $Tboth('تمت الموافقة من') ?>: ______________________</td>
  </tr></table>

  <div class="saudi-footer"><?= $rtl ? 'تم إنشاؤه بواسطة ' . \App\Models\Setting::siteName() : 'Generated by ' . \App\Models\Setting::siteName() ?></div>

<?php else: ?>

<?php
  // Exactly document.blade.php's own head-band-or-not split: modern/bold get the
  // colored band, the other 3 templates get a plain head-table — same markup either
  // way, just with the certificate's own header fields (company/VAT/CR, status
  // badge) in place of an invoice's issuer/bill-to fields.
  $headBand = in_array($tpl, ['modern', 'bold'], true);
?>
<?php if ($headBand): ?><div class="head-band"><?php endif; ?>
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
<?php if ($headBand): ?></div><?php endif; ?>

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

<?php endif; ?>

</body>
</html>
