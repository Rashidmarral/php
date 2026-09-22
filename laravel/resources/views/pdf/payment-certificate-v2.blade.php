<?php
/**
 * Stage 2's layout-family renderer for a Payment Certificate — the 8th
 * document type App\Models\InvoiceTemplate covers. Used only when
 * Company::activeInvoiceTemplateFor('payment_certificate') returns a real
 * row; PaymentCertificateController::pdf() falls back to the original
 * resources/views/pdf/payment-certificate.blade.php otherwise, unchanged.
 *
 * Kept as its own file rather than reusing document-v2.blade.php's
 * card/bilingual/letterhead partials, for the same reason the OLD
 * payment-certificate.blade.php duplicates-and-adapts document.blade.php
 * instead of sharing it: a certificate's line is cumulative-billing data
 * (contract qty/rate, previous/this cumulative, period qty/value), a
 * genuinely different shape from a simple qty/unit-price/total invoice
 * line, so its items table needs its own wider column set — the header
 * chrome, totals-panel and signature treatment are still built from the
 * exact same App\Support\Pdf\InvoiceTemplateStyles CSS as document-v2.
 *
 * Expects: lang, currency, company, project, client, certificate, lines,
 * contractValue, vatRate, vatAmount, totalDue (all already built by
 * PaymentCertificateController::pdf(), same as the old view) plus
 * invoiceTemplate (required — the resolved InvoiceTemplate row).
 */
$rtl = $lang === 'ar';
$tpl = $invoiceTemplate;
$layout = in_array($tpl->layout, ['card', 'bilingual', 'letterhead'], true) ? $tpl->layout : 'card';
$languageMode = in_array($tpl->language_mode, ['bilingual', 'english_only', 'arabic_only'], true) ? $tpl->language_mode : 'bilingual';
$showEn = $languageMode !== 'arabic_only';
$showAr = $languageMode !== 'english_only';
$primaryLocale = $languageMode === 'arabic_only' ? 'ar' : 'en';
$tableDirection = $tpl->table_direction === 'rtl' ? 'rtl' : 'ltr';
$currency = $currency ?? 'SAR';
$accent = $tpl->accent_color ?: '#16233f';
$boxed = \App\Support\Pdf\InvoiceTemplateStyles::cardUsesBoxedTotals($tpl);
$showLogo = (bool) $tpl->show_logo;
$showPartyVat = (bool) $tpl->show_party_vat_number;
$companyLogo = !empty($company->logo_path) ? ('file://' . public_path($company->logo_path)) : null;

$L = fn (string $key) => tFor($key, $primaryLocale);
$primary = fn ($en, $ar = null) => ($primaryLocale === 'ar' && $ar) ? $ar : $en;
$secondary = fn ($ar = null) => ($showAr && $primaryLocale !== 'ar') ? $ar : null;
// A single-language EN/AR pair for this certificate's own cumulative-billing
// vocabulary (Contract Qty, Gross Claim, ...) — reuses the exact Arabic
// terms already established in the old payment-certificate.blade.php's
// saudi branch, picking whichever one $primaryLocale calls for.
$C = fn (string $en, string $ar) => $primaryLocale === 'ar' ? $ar : $en;
// The EN + AR pair together, for the 'bilingual' layout's dual-language
// rows/headers — used only inside that branch below.
$bi = function (string $en, string $ar) use ($showEn, $showAr) {
    $out = $showEn ? $en : '';
    if ($showEn && $showAr) {
        $out .= '<br>';
    }
    if ($showAr) {
        $out .= '<span class="ar-text">' . $ar . '</span>';
    }
    return $out;
};

$percentComplete = $contractValue > 0 ? number_format((float) $certificate->cumulative_certified / $contractValue * 100, 1) : '0.0';
?><!doctype html>
<html lang="<?= $lang ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
@page { margin: <?= $tpl->isCompact() ? '16mm 14mm' : '20mm 16mm' ?>; }
* { box-sizing: border-box; }
body {
  font-family: 'Cairo', <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif;
  color: #1f2937;
  direction: <?= $rtl ? 'rtl' : 'ltr' ?>;
}
<?= \App\Support\Pdf\InvoiceTemplateStyles::css($tpl, $rtl) ?>
/* A certificate's 8-column cumulative-billing table needs smaller fonts than
   a simple invoice line to still fit one A4 page — same reasoning as the
   old payment-certificate.blade.php's own `.dense` modifier. */
.itpl-items-table.dense th, .itpl-bl-items-table.dense th, .itpl-lh-items-table.dense th { font-size: 7.5px; padding: 4px 3px; line-height: 1.3; }
.itpl-items-table.dense td, .itpl-bl-items-table.dense td, .itpl-lh-items-table.dense td { font-size: 8px; padding: 4px 3px; }
</style>
</head>
<body>
<div class="itpl-doc">
<?php if ($layout === 'bilingual'): ?>

  <table class="itpl-bl-header"><tr>
    <?php if ($showEn): ?>
      <td style="width:<?= $showAr ? '35%' : '70%' ?>">
        <div class="itpl-bl-company-en"><?= pdfText($company->name ?? '', 'en') ?></div>
        <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta"><?= tFor('common.vat', 'en') ?>: <?= pdfText($company->vat_number, 'en') ?></div><?php endif; ?>
        <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta"><?= tFor('pdf.cr_number', 'en') ?>: <?= pdfText($company->cr_number, 'en') ?></div><?php endif; ?>
      </td>
    <?php endif; ?>
    <?php if ($showLogo && $companyLogo): ?>
      <td style="width:30%;text-align:center;"><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"></td>
    <?php endif; ?>
    <?php if ($showAr): ?>
      <td style="width:<?= $showEn ? '35%' : '70%' ?>">
        <div class="itpl-bl-company-ar"><?= !empty($company->name_ar) ? pdfText($company->name_ar, 'ar') : e($company->name ?? '') ?></div>
        <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta ar"><?= tFor('common.vat', 'ar') ?>: <?= pdfText($company->vat_number, 'ar') ?></div><?php endif; ?>
        <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta ar"><?= tFor('pdf.cr_number', 'ar') ?>: <?= pdfText($company->cr_number, 'ar') ?></div><?php endif; ?>
      </td>
    <?php endif; ?>
  </tr></table>

  <div class="itpl-bl-title-bar">
    <?php if ($showEn): ?><?= strtoupper(tFor('pdf.doctype.payment_certificate', 'en')) ?><?php endif; ?>
    <?php if ($showEn && $showAr): ?>&nbsp;|&nbsp;<?php endif; ?>
    <?php if ($showAr): ?><span class="ar-text"><?= pdfText(tFor('pdf.doctype.payment_certificate', 'ar'), 'ar') ?></span><?php endif; ?>
  </div>

  <table class="itpl-bl-info-table"><tr>
    <?php if ($showEn): ?><td class="label-en"><?= tFor('common.number', 'en') ?></td><td class="value"><?= pdfText((string) $certificate->certificate_number, 'en') ?></td><?php endif; ?>
    <?php if ($showAr): ?><td class="label-ar"><?= tFor('common.number', 'ar') ?></td><td class="value ar-text"><?= pdfText((string) $certificate->certificate_number, 'ar') ?></td><?php endif; ?>
  </tr><tr>
    <?php if ($showEn): ?><td class="label-en"><?= tFor('common.status', 'en') ?></td><td class="value"><?= pdfText(ucfirst((string) $certificate->status), 'en') ?></td><?php endif; ?>
    <?php if ($showAr): ?><td class="label-ar"><?= tFor('common.status', 'ar') ?></td><td class="value ar-text"><?= pdfText(ucfirst((string) $certificate->status), 'ar') ?></td><?php endif; ?>
  </tr></table>

  <table class="itpl-bl-info-table"><tr>
    <?php if ($showEn): ?>
      <td class="label-en"><?= tFor('common.date', 'en') ?></td>
      <td class="value"><?= pdfText((string) $certificate->certificate_date, 'en') ?></td>
    <?php endif; ?>
    <?php if ($showAr): ?>
      <td class="label-ar"><?= tFor('common.date', 'ar') ?></td>
      <td class="value ar-text"><?= pdfText((string) $certificate->certificate_date, 'ar') ?></td>
    <?php endif; ?>
  </tr></table>

  <table class="itpl-bl-info-table"><tr>
    <?php if ($showEn): ?><td class="label-en">Project</td><td class="value" colspan="<?= $showAr ? 1 : 3 ?>"><?= pdfText($project->name ?? '', 'en') ?><?php if ($client): ?> — <?= pdfText($client->name ?? '', 'en') ?><?php endif; ?></td><?php endif; ?>
    <?php if ($showAr): ?><td class="label-ar">المشروع</td><td class="value ar-text" colspan="<?= $showEn ? 1 : 3 ?>"><?= !empty($project->name_ar) ? pdfText($project->name_ar, 'ar') : e($project->name ?? '') ?><?php if ($client): ?> — <?= !empty($client->name_ar) ? pdfText($client->name_ar, 'ar') : e($client->name ?? '') ?><?php endif; ?></td><?php endif; ?>
  </tr></table>

  <table class="itpl-bl-items-table dense" dir="<?= $tableDirection ?>">
    <thead>
      <tr>
        <th class="desc"><?= $bi('Description', 'البيان') ?></th>
        <th><?= $bi('UOM', 'الوحدة') ?></th>
        <th><?= $bi('Contract Qty', 'الكمية التعاقدية') ?></th>
        <th><?= $bi('Rate', 'سعر الوحدة') ?></th>
        <th><?= $bi('Prev. Cum.', 'السابق تراكمي') ?></th>
        <th><?= $bi('This Cum.', 'التراكمي الحالي') ?></th>
        <th><?= $bi('Period Qty', 'كمية الفترة') ?></th>
        <th><?= $bi('Period Value', 'قيمة الفترة') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $line): ?>
        <tr>
          <td class="desc"><?= pdfText($line->description, $primaryLocale) ?></td>
          <td><?= pdfText((string) $line->uom, $primaryLocale) ?></td>
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

  <table class="itpl-bl-totals-table" style="width:300px;">
    <tr><td class="label"><?= $bi('Contract Value', 'قيمة العقد الإجمالية') ?></td><td><?= number_format($contractValue, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label"><?= $bi('Cumulative Certified', 'المعتمد تراكميًا') ?></td><td><?= number_format((float) $certificate->cumulative_certified, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label"><?= $bi('% Complete', 'النسبة المكتملة') ?></td><td><?= $percentComplete ?>%</td></tr>
  </table>
  <table class="itpl-bl-totals-table" style="width:320px;<?= $rtl ? 'float:left;' : 'float:right;' ?>">
    <tr><td class="label"><?= $bi('Gross Claim', 'المطالبة الإجمالية') ?></td><td><?= number_format((float) $certificate->gross_amount, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label"><?= $bi('Retention', 'خصم الاحتجاز') ?> (<?= (float) $certificate->retention_percent ?>%)</td><td>-<?= number_format((float) $certificate->retention_amount, 2) ?> <?= $currency ?></td></tr>
    <?php if ((float) $certificate->advance_recovery_amount > 0): ?>
      <tr><td class="label"><?= $bi('Advance Recovery', 'خصم استرداد الدفعة المقدمة') ?></td><td>-<?= number_format((float) $certificate->advance_recovery_amount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <tr><td class="label"><?= $bi('Net Payable', 'صافي المستحق') ?></td><td><?= number_format((float) $certificate->net_payable, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label"><?= $bi('VAT ' . $vatRate . '%', tFor('common.vat', 'ar') . ' ' . $vatRate . '%') ?></td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
    <tr class="grand"><td class="label"><?= $bi('Total Due', 'الإجمالي المستحق') ?></td><td><?= number_format($totalDue, 2) ?> <?= $currency ?></td></tr>
  </table>
  <div class="clearfix"></div>

  <div class="itpl-bl-words"><?= $L('pdf.amount_in_words') ?>: <?= \App\Support\Pdf\NumberToWords::sar($totalDue) ?></div>

  <?php if (!empty($certificate->notes)): ?>
    <div style="clear:both;margin-top:8px;font-size:9.5px;color:#444;">
      <strong><?= $L('common.notes') ?>:</strong> <?= pdfText($certificate->notes, $primaryLocale) ?>
    </div>
  <?php endif; ?>

  <table class="itpl-bl-sign-table"><tr>
    <td style="width:50%"><?= $bi('Certified by', 'معتمد من') ?>: ______________________</td>
    <td style="width:50%"><?= $bi('Approved by', 'تمت الموافقة من') ?>: ______________________</td>
  </tr></table>

  <div class="itpl-bl-footer"><?= $rtl ? 'تم إنشاؤه بواسطة ' . \App\Models\Setting::siteName() : 'Generated by ' . \App\Models\Setting::siteName() ?></div>

<?php elseif ($layout === 'letterhead'): ?>

  <?php if (!empty($tpl->letterhead_path)): ?>
    <img src="<?= 'file://' . public_path($tpl->letterhead_path) ?>" class="itpl-lh-banner">
  <?php else: ?>
    <table class="itpl-lh-fallback-header"><tr>
      <td style="width:70%">
        <?php if ($showLogo && $companyLogo): ?><img src="<?= $companyLogo ?>" style="max-height:44px;max-width:130px;margin-bottom:6px;"><?php endif; ?>
        <div class="itpl-lh-fallback-name"><?= pdfText($primary($company->name ?? '', $company->name_ar ?? null), $primaryLocale) ?></div>
        <?php if ($secondary($company->name_ar ?? null)): ?><div class="muted small ar-text"><?= pdfText($company->name_ar, 'ar') ?></div><?php endif; ?>
      </td>
    </tr></table>
  <?php endif; ?>

  <div class="itpl-lh-title"><?= pdfText(tFor('pdf.doctype.payment_certificate', $primaryLocale), $primaryLocale) ?></div>

  <table class="itpl-lh-info-table"><tr>
    <td style="width:50%;vertical-align:top;">
      <table>
        <tr><td class="label"><?= $C('Project', 'المشروع') ?></td><td><?= pdfText($primary($project->name ?? '', $project->name_ar ?? null), $primaryLocale) ?></td></tr>
        <?php if ($client): ?><tr><td class="label"><?= $L('pdf.bill_to') ?></td><td><?= pdfText($primary($client->name ?? '', $client->name_ar ?? null), $primaryLocale) ?></td></tr><?php endif; ?>
      </table>
    </td>
    <td style="width:50%;vertical-align:top;">
      <table>
        <tr><td class="label"><?= $L('common.number') ?></td><td><?= pdfText((string) $certificate->certificate_number, $primaryLocale) ?></td></tr>
        <tr><td class="label"><?= $L('common.date') ?></td><td><?= pdfText((string) $certificate->certificate_date, $primaryLocale) ?></td></tr>
        <tr><td class="label"><?= $L('common.status') ?></td><td><?= pdfText(ucfirst((string) $certificate->status), $primaryLocale) ?></td></tr>
      </table>
    </td>
  </tr></table>

  <table class="itpl-lh-items-table dense" dir="<?= $tableDirection ?>">
    <thead>
      <tr>
        <th><?= $L('common.description') ?></th>
        <th><?= $C('UOM', 'الوحدة') ?></th>
        <th class="num"><?= $C('Contract Qty', 'الكمية التعاقدية') ?></th>
        <th class="num"><?= $C('Rate', 'سعر الوحدة') ?></th>
        <th class="num"><?= $C('Prev. Cum.', 'السابق تراكمي') ?></th>
        <th class="num"><?= $C('This Cum.', 'التراكمي الحالي') ?></th>
        <th class="num"><?= $C('Period Qty', 'كمية الفترة') ?></th>
        <th class="num"><?= $C('Period Value', 'قيمة الفترة') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $line): ?>
        <tr>
          <td><?= pdfText($line->description, $primaryLocale) ?></td>
          <td><?= pdfText((string) $line->uom, $primaryLocale) ?></td>
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

  <div class="itpl-lh-words"><?= $L('pdf.amount_in_words') ?>: <?= \App\Support\Pdf\NumberToWords::sar($totalDue) ?></div>

  <table class="itpl-lh-totals-table">
    <tr><td><?= $C('Gross Claim', 'المطالبة الإجمالية') ?></td><td class="num"><?= $currency ?> <?= number_format((float) $certificate->gross_amount, 2) ?></td></tr>
    <tr><td><?= $C('Retention', 'خصم الاحتجاز') ?> (<?= (float) $certificate->retention_percent ?>%)</td><td class="num">-<?= $currency ?> <?= number_format((float) $certificate->retention_amount, 2) ?></td></tr>
    <tr><td><?= $L('common.vat') ?></td><td class="num"><?= $currency ?> <?= number_format($vatAmount, 2) ?></td></tr>
    <tr class="grand"><td><?= $C('Total Due', 'الإجمالي المستحق') ?></td><td class="num"><?= $currency ?> <?= number_format($totalDue, 2) ?></td></tr>
  </table>
  <div class="clearfix"></div>

  <?php if (!empty($certificate->notes)): ?>
    <div class="notes-box" style="margin-top:16px;clear:both;">
      <h4 class="muted small" style="text-transform:uppercase;"><?= $L('common.notes') ?></h4>
      <div><?= pdfText($certificate->notes, $primaryLocale) ?></div>
    </div>
  <?php endif; ?>

  <div class="itpl-lh-signature">
    <div class="line"></div>
    <div class="muted small" style="text-align:<?= $rtl ? 'left' : 'right' ?>;"><?= $C('Certified by', 'معتمد من') ?></div>
  </div>

  <div class="itpl-lh-footer"><?= $primaryLocale === 'ar' ? 'تم إنشاؤه بواسطة ' . \App\Models\Setting::siteName() : 'Generated by ' . \App\Models\Setting::siteName() ?></div>

<?php else: ?>

  <div class="itpl-card-topbar"></div>

  <table class="itpl-card-header"><tr>
    <td style="width:60%">
      <?php if ($showLogo && $companyLogo): ?><img src="<?= $companyLogo ?>" style="max-height:48px;max-width:140px;margin-bottom:6px;"><?php endif; ?>
      <div class="itpl-card-company-name"><?= pdfText($primary($company->name ?? '', $company->name_ar ?? null), $primaryLocale) ?></div>
      <?php if ($secondary($company->name_ar ?? null)): ?><div class="muted small ar-text"><?= pdfText($company->name_ar, 'ar') ?></div><?php endif; ?>
      <?php if ($showPartyVat && !empty($company->vat_number)): ?><span class="itpl-card-vat-badge"><?= $L('common.vat') ?> <?= pdfText($company->vat_number, $primaryLocale) ?></span><?php endif; ?>
    </td>
    <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
      <span class="itpl-card-doc-badge"><?= pdfText(tFor('pdf.doctype.payment_certificate', $primaryLocale), $primaryLocale) ?></span>
      <div class="muted" style="margin-top:8px;">#<?= pdfText((string) $certificate->certificate_number, $primaryLocale) ?></div>
      <div class="muted small"><?= pdfText((string) $certificate->certificate_date, $primaryLocale) ?></div>
      <div class="muted small" style="margin-top:4px;"><?= pdfText(ucfirst((string) $certificate->status), $primaryLocale) ?></div>
    </td>
  </tr></table>

  <table style="margin-top:16px;"><tr>
    <td style="width:50%;vertical-align:top;padding-<?= $rtl ? 'left' : 'right' ?>:8px;">
      <div class="itpl-card-box">
        <h4><?= $C('Project', 'المشروع') ?></h4>
        <div style="font-weight:700;"><?= pdfText($primary($project->name ?? '', $project->name_ar ?? null), $primaryLocale) ?></div>
        <?php if ($client): ?>
          <h4 style="margin-top:8px;"><?= $L('pdf.bill_to') ?></h4>
          <div><?= pdfText($primary($client->name ?? '', $client->name_ar ?? null), $primaryLocale) ?></div>
        <?php endif; ?>
      </div>
    </td>
    <td style="width:50%;vertical-align:top;padding-<?= $rtl ? 'right' : 'left' ?>:8px;">
      <div class="itpl-card-box">
        <h4><?= $C('% Complete', 'النسبة المكتملة') ?></h4>
        <div style="font-weight:700;font-size:16px;"><?= $percentComplete ?>%</div>
      </div>
    </td>
  </tr></table>

  <div class="itpl-table-card">
    <table class="itpl-items-table dense" dir="<?= $tableDirection ?>">
      <thead>
        <tr>
          <th><?= $L('common.description') ?></th>
          <th class="num"><?= $C('UOM', 'الوحدة') ?></th>
          <th class="num"><?= $C('Contract Qty', 'الكمية التعاقدية') ?></th>
          <th class="num"><?= $C('Rate', 'سعر الوحدة') ?></th>
          <th class="num"><?= $C('Prev. Cum.', 'السابق تراكمي') ?></th>
          <th class="num"><?= $C('This Cum.', 'التراكمي الحالي') ?></th>
          <th class="num"><?= $C('Period Qty', 'كمية الفترة') ?></th>
          <th class="num"><?= $C('Period Value', 'قيمة الفترة') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lines as $line): ?>
          <tr>
            <td><?= pdfText($line->description, $primaryLocale) ?></td>
            <td class="num"><?= pdfText((string) $line->uom, $primaryLocale) ?></td>
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
  </div>

  <div class="itpl-totals-wrap">
    <table class="itpl-totals-box <?= $boxed ? 'boxed' : 'plain' ?>">
      <tr><td><?= $C('Gross Claim', 'المطالبة الإجمالية') ?></td><td class="num"><?= $currency ?> <?= number_format((float) $certificate->gross_amount, 2) ?></td></tr>
      <tr><td><?= $C('Retention', 'خصم الاحتجاز') ?> (<?= (float) $certificate->retention_percent ?>%)</td><td class="num">-<?= $currency ?> <?= number_format((float) $certificate->retention_amount, 2) ?></td></tr>
      <?php if ((float) $certificate->advance_recovery_amount > 0): ?>
        <tr><td><?= $C('Advance Recovery', 'خصم استرداد الدفعة المقدمة') ?></td><td class="num">-<?= $currency ?> <?= number_format((float) $certificate->advance_recovery_amount, 2) ?></td></tr>
      <?php endif; ?>
      <tr><td><?= $C('Net Payable', 'صافي المستحق') ?></td><td class="num"><?= $currency ?> <?= number_format((float) $certificate->net_payable, 2) ?></td></tr>
      <tr><td><?= $L('common.vat') ?> (<?= $vatRate ?>%)</td><td class="num"><?= $currency ?> <?= number_format($vatAmount, 2) ?></td></tr>
      <tr class="grand"><td><?= $C('Total Due', 'الإجمالي المستحق') ?></td><td class="num"><?= $currency ?> <?= number_format($totalDue, 2) ?></td></tr>
    </table>
  </div>
  <div class="clearfix"></div>

  <?php if (!empty($certificate->notes)): ?>
    <div class="notes-box" style="margin-top:18px;clear:both;">
      <h4 class="muted small" style="text-transform:uppercase;"><?= $L('common.notes') ?></h4>
      <div><?= pdfText($certificate->notes, $primaryLocale) ?></div>
    </div>
  <?php endif; ?>

  <table class="itpl-signature" style="width:100%;"><tr>
    <td style="width:50%;">
      <div class="line"></div>
      <div class="muted small"><?= $C('Certified by', 'معتمد من') ?></div>
    </td>
    <td style="width:50%;">
      <div class="line"></div>
      <div class="muted small"><?= $C('Approved by', 'تمت الموافقة من') ?></div>
    </td>
  </tr></table>

  <div class="itpl-footer-note"><?= $primaryLocale === 'ar' ? 'تم إنشاؤه بواسطة ' . \App\Models\Setting::siteName() : 'Generated by ' . \App\Models\Setting::siteName() ?></div>

<?php endif; ?>
</div>
</body>
</html>
