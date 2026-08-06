<?php
use App\Core\View;

$rtl = $lang === 'ar';
$tpl = in_array($template, ['classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $template : 'modern';
$T = fn($v) => View::pdfText($v, $lang);
$Tboth = fn($v) => View::pdfText($v, 'ar');
$currency = $currency ?? 'SAR';
$isInvoice = stripos((string) $docType, 'invoice') !== false || str_contains((string) $docType, 'فاتورة');
?><!doctype html>
<html lang="<?= $lang ?>" dir="<?= $rtl ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<style>
@page { margin: 20mm 16mm; }
* { box-sizing: border-box; }
body {
  font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif;
  color: #16211f;
  font-size: 12px;
  direction: <?= $rtl ? 'rtl' : 'ltr' ?>;
}
table { width: 100%; border-collapse: collapse; }
.head-table td { vertical-align: top; padding: 0; }
.doc-title { font-size: 22px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
.doc-number { font-size: 13px; color: #555; margin-top: 4px; }
.company-name { font-size: 17px; font-weight: 700; }
.meta-line { font-size: 11px; color: #555; margin-top: 2px; }
.section-title { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 4px; }
.party-name { font-size: 13px; font-weight: 700; }
.items-table { margin-top: 18px; }
.items-table th { font-size: 10.5px; text-transform: uppercase; letter-spacing: .03em; padding: 8px 10px; text-align: <?= $rtl ? 'right' : 'left' ?>; }
.items-table td { padding: 8px 10px; font-size: 11.5px; }
.items-table .num { text-align: <?= $rtl ? 'left' : 'right' ?>; }
.totals-table { width: 260px; <?= $rtl ? 'float:left;' : 'float:right;' ?> margin-top: 14px; }
.totals-table td { padding: 5px 10px; font-size: 11.5px; }
.totals-table .num { text-align: <?= $rtl ? 'left' : 'right' ?>; }
.totals-table .grand { font-size: 15px; font-weight: 700; }
.zatca-qr { <?= $rtl ? 'float:right;' : 'float:left;' ?> margin-top: 14px; text-align: center; width: 120px; }
.zatca-qr-label { font-size: 8.5px; color: #777; margin-top: 4px; }
.notes-box { clear: both; margin-top: 60px; padding-top: 10px; font-size: 10.5px; color: #666; }
.status-badge { display: inline-block; padding: 3px 12px; border-radius: 3px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
.footer-note { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 9.5px; color: #999; }

/* ---- Modern template ---- */
.tpl-modern .head-band { background: #0f6e5f; color: #fff; padding: 22px 24px; margin: -20mm -16mm 20px; }
.tpl-modern .head-band .doc-title, .tpl-modern .head-band .doc-number { color: #fff; }
.tpl-modern .head-band .company-name { color: #fff; }
.tpl-modern .head-band .meta-line { color: #d8ece7; }
.tpl-modern .items-table thead { background: #e6f4f1; }
.tpl-modern .items-table thead th { color: #0a4d42; }
.tpl-modern .items-table td { border-bottom: 1px solid #e6ecea; }
.tpl-modern .items-table tr:nth-child(even) td { background: #f7faf9; }
.tpl-modern .totals-table .grand { color: #0a4d42; border-top: 2px solid #0f6e5f; }
.tpl-modern .status-badge { background: #e6f4f1; color: #0a4d42; }

/* ---- Classic template ---- */
.tpl-classic body, .tpl-classic { font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Serif'" ?>, serif; }
.tpl-classic .head-table { border-bottom: 3px double #16211f; padding-bottom: 14px; margin-bottom: 16px; }
.tpl-classic .doc-title { font-weight: 700; }
.tpl-classic .items-table thead { border-top: 1.5px solid #16211f; border-bottom: 1.5px solid #16211f; }
.tpl-classic .items-table th { font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif; }
.tpl-classic .items-table td { border-bottom: 0.5px solid #ccc; }
.tpl-classic .totals-table .grand { border-top: 1.5px solid #16211f; }
.tpl-classic .status-badge { border: 1px solid #16211f; background: #fff; color: #16211f; }

/* ---- Minimal template ---- */
.tpl-minimal .head-table { margin-bottom: 26px; }
.tpl-minimal .doc-title { font-weight: 300; letter-spacing: .12em; color: #555; }
.tpl-minimal .company-name { font-weight: 400; }
.tpl-minimal .items-table thead th { border-bottom: 1px solid #16211f; color: #16211f; }
.tpl-minimal .items-table td { border-bottom: 1px solid #eee; }
.tpl-minimal .totals-table .grand { border-top: 1px solid #16211f; }
.tpl-minimal .status-badge { background: #f2f2f2; color: #444; }

/* ---- Bold template ---- */
.tpl-bold .head-band { background: #a8790a; color: #fff; padding: 26px 24px; margin: -20mm -16mm 22px; }
.tpl-bold .head-band .doc-title, .tpl-bold .head-band .doc-number, .tpl-bold .head-band .company-name { color: #fff; }
.tpl-bold .head-band .meta-line { color: #fbe9c6; }
.tpl-bold .doc-title { font-weight: 800; font-size: 26px; }
.tpl-bold .party-name { font-size: 15px; }
.tpl-bold .items-table thead { background: #16211f; }
.tpl-bold .items-table thead th { color: #fff; }
.tpl-bold .items-table tr:nth-child(even) td { background: #fbf3e2; }
.tpl-bold .items-table td { border-bottom: 1px solid #f0e2c4; }
.tpl-bold .totals-table .grand { color: #a8790a; border-top: 3px solid #a8790a; font-size: 17px; }
.tpl-bold .status-badge { background: #a8790a; color: #fff; }

/* ---- Elegant template ---- */
.tpl-elegant body, .tpl-elegant { font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Serif'" ?>, serif; color: #2a2a28; }
.tpl-elegant .head-table { margin-bottom: 8px; }
.tpl-elegant .doc-title { font-weight: 400; letter-spacing: .2em; font-size: 15px; color: #8a7550; }
.tpl-elegant .company-name { font-weight: 700; font-size: 19px; letter-spacing: .03em; }
.tpl-elegant .doc-number { color: #8a7550; }
.tpl-elegant .section-title { letter-spacing: .12em; }
.tpl-elegant .items-table { margin-top: 26px; }
.tpl-elegant .items-table thead th { border-top: 0.75px solid #8a7550; border-bottom: 0.75px solid #8a7550; font-weight: 400; letter-spacing: .08em; color: #8a7550; }
.tpl-elegant .items-table td { border-bottom: 0.5px solid #e7e1d3; font-family: <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif; }
.tpl-elegant .totals-table .grand { border-top: 0.75px solid #8a7550; color: #8a7550; }
.tpl-elegant .status-badge { border: 0.75px solid #8a7550; background: #fff; color: #8a7550; letter-spacing: .08em; }

/* ---- Saudi (ZATCA-standard bilingual) template ---- */
.tpl-saudi body, .tpl-saudi { font-family: 'DejaVu Sans', 'Noto Naskh Arabic', sans-serif; font-size: 11px; }
.tpl-saudi .saudi-header { border: 1.5px solid #16211f; padding: 10px 14px; }
.tpl-saudi .saudi-header td { vertical-align: middle; }
.tpl-saudi .saudi-company-en { font-size: 13px; font-weight: 700; }
.tpl-saudi .saudi-company-ar { font-size: 13px; font-weight: 700; direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', sans-serif; }
.tpl-saudi .saudi-meta-en { font-size: 9px; color: #444; margin-top: 2px; }
.tpl-saudi .saudi-meta-ar { font-size: 9px; color: #444; margin-top: 2px; direction: rtl; text-align: right; font-family: 'Noto Naskh Arabic', sans-serif; }
.tpl-saudi .saudi-logo { text-align: center; }
.tpl-saudi .saudi-title-bar { text-align: center; background: #16211f; color: #fff; border: 1.5px solid #16211f; border-top: none; padding: 7px; font-weight: 700; font-size: 13px; }
.tpl-saudi .saudi-info-table { border: 1px solid #16211f; border-top: none; }
.tpl-saudi .saudi-info-table td { border: 1px solid #16211f; padding: 5px 8px; font-size: 9.5px; }
.tpl-saudi .saudi-info-table .en { text-align: left; }
.tpl-saudi .saudi-info-table .ar { text-align: right; direction: rtl; font-family: 'Noto Naskh Arabic', sans-serif; }
.tpl-saudi .saudi-items-table { margin-top: 0; border: 1px solid #16211f; }
.tpl-saudi .saudi-items-table th { border: 1px solid #16211f; padding: 6px 8px; font-size: 9.5px; background: #eef1f0; text-align: center; }
.tpl-saudi .saudi-items-table td { border: 1px solid #16211f; padding: 6px 8px; font-size: 10px; text-align: center; }
.tpl-saudi .saudi-items-table .desc { text-align: <?= $rtl ? 'right' : 'left' ?>; }
.tpl-saudi .saudi-totals-table { margin-top: 10px; }
.tpl-saudi .saudi-totals-table td { border: 1px solid #16211f; padding: 6px 10px; font-size: 10.5px; }
.tpl-saudi .saudi-totals-table .label { background: #eef1f0; font-weight: 700; }
.tpl-saudi .saudi-totals-table .grand td { font-weight: 700; font-size: 12.5px; background: #f7f0dc; }
.tpl-saudi .saudi-words { margin-top: 8px; border: 1px solid #16211f; background: #16211f; color: #fff; padding: 7px 10px; font-size: 10px; text-align: center; }
.tpl-saudi .saudi-sign-table { margin-top: 22px; }
.tpl-saudi .saudi-sign-table td { font-size: 9.5px; padding-top: 26px; border-top: 0.75px solid #999; }
.tpl-saudi .saudi-qr-cell { width: 110px; vertical-align: top; }
.tpl-saudi .saudi-footer { margin-top: 16px; text-align: center; font-size: 9px; color: #555; border-top: 1px solid #16211f; padding-top: 6px; }
</style>
</head>
<body class="tpl-<?= $tpl ?>">

<?php if ($tpl === 'saudi'): ?>
  <?php
    $titleEn = $isInvoice ? 'TAX INVOICE' : 'QUOTATION';
    $titleAr = $isInvoice ? 'فاتورة ضريبية' : 'عرض سعر';
    $companyNameAr = $companyNameAr ?? '';
  ?>
  <table class="saudi-header"><tr>
    <td style="width:35%">
      <div class="saudi-company-en"><?= View::pdfText($issuer['name'] ?? '', 'en') ?></div>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="saudi-meta-en"><?= View::pdfText($line, 'en') ?></div><?php endforeach; ?>
    </td>
    <td class="saudi-logo" style="width:30%">
      <?php if (!empty($companyLogo)): ?><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"><?php endif; ?>
    </td>
    <td style="width:35%">
      <div class="saudi-company-ar"><?= $companyNameAr !== '' ? $Tboth($companyNameAr) : View::e($issuer['name'] ?? '') ?></div>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="saudi-meta-ar"><?= View::e($line) ?></div><?php endforeach; ?>
    </td>
  </tr></table>

  <div class="saudi-title-bar"><?= $titleEn ?> &nbsp;|&nbsp; <?= $Tboth($titleAr) ?></div>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:33%">Document No: <?= $T($docNumber) ?></td>
    <td class="en" style="width:34%"><?= $isInvoice ? 'Payment: ' . $T($status ?? '') : ('Valid until: ' . $T($validUntil ?? '—')) ?></td>
    <td class="ar" style="width:33%"><?= $Tboth('التاريخ') ?>: <?= $T($docDate) ?></td>
  </tr></table>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:50%">
      Client: <?= View::pdfText($billTo['name'] ?? '—', 'en') ?>
      <?php foreach (($billTo['meta'] ?? []) as $line): ?><br><?= View::pdfText($line, 'en') ?><?php endforeach; ?>
    </td>
    <td class="ar" style="width:50%">
      <?= $Tboth('اسم العميل') ?>: <?= View::e($billTo['name'] ?? '—') ?>
    </td>
  </tr></table>

  <table class="saudi-items-table">
    <thead>
      <tr>
        <th style="width:8%">#</th>
        <th class="desc" style="width:44%">Description / <?= $Tboth('البيان') ?></th>
        <th style="width:12%">Qty / <?= $Tboth('الكمية') ?></th>
        <th style="width:18%">Unit Price / <?= $Tboth('سعر الوحدة') ?></th>
        <th style="width:18%">Total / <?= $Tboth('الأجمالي') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $index => $item): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <td class="desc"><?= View::pdfText($item['description'], 'en') ?></td>
          <td><?= $T((string) $item['qty']) ?></td>
          <td><?= number_format((float) $item['unit_price'], 2) ?></td>
          <td><?= number_format((float) $item['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="saudi-totals-table" style="width:320px;<?= $rtl ? 'float:right;' : 'float:left;' ?>">
    <tr><td class="label">Total / <?= $Tboth('الاجمالي') ?></td><td><?= number_format($subtotal + ($discountAmount ?? 0), 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">Discount / <?= $Tboth('الخصم') ?></td><td><?= number_format($discountAmount ?? 0, 2) ?> <?= $currency ?></td></tr>
    <tr><td class="label">Total after discount / <?= $Tboth('الأجمالي بعد الخصم') ?></td><td><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
    <?php if (isset($vatAmount)): ?>
      <tr><td class="label">VAT <?= $vatRate ?>% / <?= $Tboth('ضريبة القيمة المضافة') ?></td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td class="label">Amount Due / <?= $Tboth('المستحق') ?></td><td><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
  </table>
  <div style="clear:both;"></div>

  <div class="saudi-words">Amount in words: <?= \App\Core\NumberToWords::sar($total) ?></div>

  <table class="saudi-sign-table"><tr>
    <?php if (!empty($qrCode)): ?>
      <td class="saudi-qr-cell"><img src="<?= $qrCode ?>" width="100" height="100"></td>
    <?php endif; ?>
    <td style="width:50%">Recipient / <?= $Tboth('المستلم') ?>: ______________________</td>
    <td style="width:50%">Seller / <?= $Tboth('البائع') ?>: ______________________</td>
  </tr></table>

  <div class="saudi-footer"><?= $T($footerNote ?? '') ?></div>

<?php else: ?>

<?php if (in_array($tpl, ['modern', 'bold'], true)): ?>
  <div class="head-band">
    <table class="head-table"><tr>
      <td style="width:60%">
        <div class="company-name"><?= $T($issuer['name'] ?? '') ?></div>
        <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="meta-line"><?= $T($line) ?></div><?php endforeach; ?>
      </td>
      <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
        <div class="doc-title"><?= $T($docType) ?></div>
        <div class="doc-number">#<?= $T($docNumber) ?></div>
        <div class="meta-line"><?= $T($docDate) ?></div>
      </td>
    </tr></table>
  </div>
<?php else: ?>
  <table class="head-table"><tr>
    <td style="width:60%">
      <div class="company-name"><?= $T($issuer['name'] ?? '') ?></div>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="meta-line"><?= $T($line) ?></div><?php endforeach; ?>
    </td>
    <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
      <div class="doc-title"><?= $T($docType) ?></div>
      <div class="doc-number">#<?= $T($docNumber) ?></div>
      <div class="meta-line"><?= $T($docDate) ?></div>
    </td>
  </tr></table>
<?php endif; ?>

<table class="head-table"><tr>
  <td style="width:60%">
    <?php if (!empty($billTo)): ?>
      <div class="section-title"><?= $T($lang === 'ar' ? 'إلى' : 'Bill To') ?></div>
      <div class="party-name"><?= $T($billTo['name'] ?? '') ?></div>
      <?php foreach (($billTo['meta'] ?? []) as $line): ?><div class="meta-line"><?= $T($line) ?></div><?php endforeach; ?>
    <?php endif; ?>
  </td>
  <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
    <?php if (!empty($status)): ?><span class="status-badge"><?= $T($status) ?></span><?php endif; ?>
    <?php if (!empty($validUntil)): ?><div class="meta-line" style="margin-top:6px;"><?= $T($lang === 'ar' ? 'صالح حتى' : 'Valid until') ?>: <?= $T($validUntil) ?></div><?php endif; ?>
  </td>
</tr></table>

<table class="items-table">
  <thead>
    <tr>
      <th><?= $T($lang === 'ar' ? 'الوصف' : 'Description') ?></th>
      <th class="num"><?= $T($lang === 'ar' ? 'الكمية' : 'Qty') ?></th>
      <th class="num"><?= $T($lang === 'ar' ? 'سعر الوحدة' : 'Unit Price') ?></th>
      <th class="num"><?= $T($lang === 'ar' ? 'الإجمالي' : 'Total') ?></th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($items as $item): ?>
      <tr>
        <td><?= $T($item['description']) ?></td>
        <td class="num"><?= $T((string) $item['qty']) ?></td>
        <td class="num"><?= number_format((float) $item['unit_price'], 2) ?></td>
        <td class="num"><?= number_format((float) $item['total'], 2) ?></td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="totals-table">
  <tr><td><?= $T($lang === 'ar' ? 'المجموع الفرعي' : 'Subtotal') ?></td><td class="num"><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
  <?php if (!empty($discountAmount)): ?>
    <tr><td><?= $T($lang === 'ar' ? 'الخصم' : 'Discount') ?> (<?= $discountPercent ?>%)</td><td class="num">-<?= number_format($discountAmount, 2) ?> <?= $currency ?></td></tr>
  <?php endif; ?>
  <?php if (isset($vatAmount)): ?>
    <tr><td><?= $T($lang === 'ar' ? 'ضريبة القيمة المضافة' : 'VAT') ?> (<?= $vatRate ?>%)</td><td class="num"><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
  <?php endif; ?>
  <tr class="grand"><td><?= $T($lang === 'ar' ? 'الإجمالي' : 'Total') ?></td><td class="num"><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
</table>

<?php if (!empty($notes)): ?>
  <div class="notes-box">
    <div class="section-title"><?= $T($lang === 'ar' ? 'ملاحظات' : 'Notes') ?></div>
    <div><?= $T($notes) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($qrCode)): ?>
  <div class="zatca-qr">
    <img src="<?= $qrCode ?>" width="110" height="110">
    <div class="zatca-qr-label"><?= $T($lang === 'ar' ? 'رمز الاستجابة السريعة (هيئة الزكاة والضريبة)' : 'ZATCA QR Code') ?></div>
  </div>
<?php endif; ?>

<div class="footer-note"><?= $T($footerNote ?? '') ?></div>

<?php endif; ?>

</body>
</html>
