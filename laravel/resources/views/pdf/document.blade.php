<?php
$rtl = $lang === 'ar';
$tpl = in_array($template, ['classic', 'minimal', 'bold', 'elegant', 'saudi'], true) ? $template : 'modern';
$T = fn($v) => pdfText($v, $lang);
$Tboth = fn($v) => pdfText($v, 'ar');
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
  /* Cairo (Latin + Arabic) is the default; DejaVu Sans/Noto Naskh Arabic stay as
     documented fallbacks in case an edge-case glyph is ever missing from Cairo. */
  font-family: 'Cairo', <?= $rtl ? "'Noto Naskh Arabic'" : "'DejaVu Sans'" ?>, sans-serif;
  color: #16211f;
  font-size: 12px;
  direction: <?= $rtl ? 'rtl' : 'ltr' ?>;
}
table { width: 100%; border-collapse: collapse; }
<?= \App\Support\Pdf\PdfTemplateStyles::baseChrome() ?>
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
<?= \App\Support\Pdf\PdfTemplateStyles::statusAndFooterChrome() ?>

<?= \App\Support\Pdf\PdfTemplateStyles::variants($rtl) ?>

<?= \App\Support\Pdf\PdfTemplateStyles::saudiChrome($rtl) ?>
</style>
</head>
<body class="tpl-<?= $tpl ?>">

<?php if ($tpl === 'saudi'): ?>
  <?php
    $titleEn = $isInvoice ? 'TAX INVOICE' : 'QUOTATION';
    $titleAr = $isInvoice ? 'فاتورة ضريبية' : 'عرض سعر';
    $companyNameAr = $companyNameAr ?? '';

    // Everything below is new, optional data only InvoiceController::pdf()
    // passes today — every other document type sharing this file (estimates,
    // purchase orders, credit/debit notes, zakat, recurring invoices), and
    // any invoice rendered before this feature existed, simply never sets
    // these, so they default to null/0/[] here and every block below stays
    // silent (exactly its old output) when they're empty.
    $sellerAddress = $sellerAddress ?? [];
    $billToAddress = $billToAddress ?? [];
    $paymentMethod = $paymentMethod ?? null;
    $dateOfSupply = $dateOfSupply ?? null;
    $otherCharges = (float) ($otherCharges ?? 0);
    $taxableAmount = $taxableAmount ?? ((float) $total - (float) ($vatAmount ?? 0));
    $paidAmount = $paidAmount ?? null;
    $balanceDue = $balanceDue ?? null;

    // Same "Label: value" line builder as document-v2's partials — label
    // looked up via tFor() (real, translatable, DB-overridable text) and
    // both label and value shaped/escaped via pdfText(), for either
    // explicit language column on this always-bilingual layout.
    $addressLines = fn (array $address, string $locale): array => \App\Support\Pdf\AddressFormatter::lines($address, $locale);

    // Real, translatable (en.json/ar.json, DB-overridable) bilingual labels
    // for every NEW piece of text this branch adds below — "EN / AR", the
    // same visual convention the rest of this always-bilingual layout uses,
    // but sourced from tFor() like every other label in the app rather than
    // a hardcoded literal string.
    $biEn = fn (string $key): string => pdfText(tFor($key, 'en'), 'en');
    $biAr = fn (string $key): string => pdfText(tFor($key, 'ar'), 'ar');
    $biBoth = fn (string $key): string => $biEn($key) . ' / ' . $biAr($key);

    // Per-line taxable/VAT/tax-rate figures and the VAT-summary grouping —
    // same computation document-v2.blade.php's shared $itemRows already
    // does for the newer template system, needed here too now that this
    // branch renders the same richer item/VAT-summary columns.
    $saudiRate = (float) ($vatRate ?? 0);
    $saudiItemRows = array_map(function ($item) use ($saudiRate) {
        $qty = (float) ($item['qty'] ?? 0);
        $unitPrice = (float) ($item['unit_price'] ?? 0);
        $discount = (float) ($item['discount'] ?? 0);
        $taxable = round(max(0, $qty * $unitPrice - $discount), 2);

        return [
            'item_code' => $item['item_code'] ?? null,
            'unit' => $item['unit'] ?? null,
            'description' => (string) ($item['description'] ?? ''),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'tax_code' => $item['tax_code'] ?? 'S',
            'tax_rate' => $saudiRate,
            'taxable' => $taxable,
            'vat' => round($taxable * $saudiRate / 100, 2),
            'total' => (float) ($item['total'] ?? $taxable),
        ];
    }, $items ?? []);
    $showItemCodeColumn = (bool) array_filter($saudiItemRows, fn ($row) => !empty($row['item_code']));
    $showUnitColumn = (bool) array_filter($saudiItemRows, fn ($row) => !empty($row['unit']));
    $showDiscountColumn = (bool) array_filter($saudiItemRows, fn ($row) => ($row['discount'] ?? 0) > 0);
    $saudiVatSummary = [];
    foreach ($saudiItemRows as $row) {
        $key = $row['tax_code'] . '|' . $row['tax_rate'];
        if (!isset($saudiVatSummary[$key])) {
            $saudiVatSummary[$key] = ['tax_code' => $row['tax_code'], 'tax_rate' => $row['tax_rate'], 'taxable' => 0.0, 'vat' => 0.0, 'total' => 0.0];
        }
        $saudiVatSummary[$key]['taxable'] += $row['taxable'];
        $saudiVatSummary[$key]['vat'] += $row['vat'];
        $saudiVatSummary[$key]['total'] += $row['taxable'] + $row['vat'];
    }
    $saudiVatSummary = array_values($saudiVatSummary);
  ?>
  <table class="saudi-header"><tr>
    <td style="width:35%">
      <div class="saudi-company-en"><?= pdfText($issuer['name'] ?? '', 'en') ?></div>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="saudi-meta-en"><?= pdfText($line, 'en') ?></div><?php endforeach; ?>
      <?php foreach ($addressLines($sellerAddress, 'en') as $line): ?><div class="saudi-meta-en"><?= $line ?></div><?php endforeach; ?>
    </td>
    <td class="saudi-logo" style="width:30%">
      <?php if (!empty($companyLogo)): ?><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"><?php endif; ?>
    </td>
    <td style="width:35%">
      <div class="saudi-company-ar"><?= $companyNameAr !== '' ? $Tboth($companyNameAr) : e($issuer['name'] ?? '') ?></div>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?><div class="saudi-meta-ar"><?= e($line) ?></div><?php endforeach; ?>
      <?php foreach ($addressLines($sellerAddress, 'ar') as $line): ?><div class="saudi-meta-ar"><?= $line ?></div><?php endforeach; ?>
    </td>
  </tr></table>

  <div class="saudi-title-bar"><?= $titleEn ?> &nbsp;|&nbsp; <?= $Tboth($titleAr) ?></div>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:33%">Document No: <?= $T($docNumber) ?></td>
    <td class="en" style="width:34%"><?= $isInvoice ? 'Payment: ' . $T($status ?? '') : ('Valid until: ' . $T($validUntil ?? '—')) ?></td>
    <td class="ar" style="width:33%"><?= $Tboth('التاريخ') ?>: <?= $T($docDate) ?></td>
  </tr></table>

  <?php if (!empty($paymentMethod) || !empty($dateOfSupply)): ?>
  <table class="saudi-info-table"><tr>
    <td class="en" style="width:50%">
      <?= $biEn('pdf.payment_method') ?>: <?= !empty($paymentMethod) ? $biEn('pdf.payment_method.' . $paymentMethod) : '—' ?>
    </td>
    <td class="ar" style="width:50%">
      <?= $biAr('pdf.date_of_supply') ?>: <?= !empty($dateOfSupply) ? pdfText((string) $dateOfSupply, 'ar') : '—' ?>
    </td>
  </tr></table>
  <?php endif; ?>

  <table class="saudi-info-table"><tr>
    <td class="en" style="width:50%">
      Client: <?= pdfText($billTo['name'] ?? '—', 'en') ?>
      <?php foreach (($billTo['meta'] ?? []) as $line): ?><br><?= pdfText($line, 'en') ?><?php endforeach; ?>
      <?php foreach ($addressLines($billToAddress, 'en') as $line): ?><br><?= $line ?><?php endforeach; ?>
    </td>
    <td class="ar" style="width:50%">
      <?= $Tboth('اسم العميل') ?>: <?= e($billTo['name'] ?? '—') ?>
      <?php foreach ($addressLines($billToAddress, 'ar') as $line): ?><br><?= $line ?><?php endforeach; ?>
    </td>
  </tr></table>

  <table class="saudi-items-table">
    <thead>
      <tr>
        <th style="width:6%">#</th>
        <?php if ($showItemCodeColumn): ?><th style="width:10%"><?= $biBoth('pdf.item_code') ?></th><?php endif; ?>
        <th class="desc" style="width:<?= $showItemCodeColumn ? '28%' : '34%' ?>">Description / <?= $Tboth('البيان') ?></th>
        <?php if ($showUnitColumn): ?><th style="width:8%"><?= $biBoth('common.unit') ?></th><?php endif; ?>
        <th style="width:8%">Qty / <?= $Tboth('الكمية') ?></th>
        <th style="width:10%">Unit Price / <?= $Tboth('سعر الوحدة') ?></th>
        <?php if ($showDiscountColumn): ?><th style="width:8%"><?= $biBoth('pdf.discount') ?></th><?php endif; ?>
        <th style="width:8%"><?= $biBoth('pdf.taxable_amount') ?></th>
        <th style="width:6%"><?= $biBoth('pdf.tax_rate') ?></th>
        <th style="width:5%"><?= $biBoth('pdf.tax_code') ?></th>
        <th style="width:8%"><?= $biBoth('pdf.vat_amount') ?></th>
        <th style="width:10%">Total / <?= $Tboth('الأجمالي') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($saudiItemRows as $index => $item): ?>
        <tr>
          <td><?= $index + 1 ?></td>
          <?php if ($showItemCodeColumn): ?><td><?= pdfText((string) ($item['item_code'] ?? ''), 'en') ?></td><?php endif; ?>
          <td class="desc"><?= pdfText($item['description'], 'en') ?></td>
          <?php if ($showUnitColumn): ?><td><?= pdfText((string) ($item['unit'] ?? ''), 'en') ?></td><?php endif; ?>
          <td><?= $T((string) $item['qty']) ?></td>
          <td><?= number_format($item['unit_price'], 2) ?></td>
          <?php if ($showDiscountColumn): ?><td><?= $item['discount'] > 0 ? number_format($item['discount'], 2) : '—' ?></td><?php endif; ?>
          <td><?= number_format($item['taxable'], 2) ?></td>
          <td><?= rtrim(rtrim(number_format($item['tax_rate'], 2), '0'), '.') ?>%</td>
          <td><?= pdfText($item['tax_code'], 'en') ?></td>
          <td><?= number_format($item['vat'], 2) ?></td>
          <td><?= number_format($item['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?php if (!empty($saudiVatSummary)): ?>
  <div class="saudi-vat-summary-title"><?= $biBoth('pdf.vat_summary') ?></div>
  <table class="saudi-vat-summary-table">
    <thead>
      <tr>
        <th><?= $biBoth('pdf.tax_code') ?></th>
        <th><?= $biBoth('pdf.tax_rate') ?></th>
        <th><?= $biBoth('pdf.taxable_amount') ?></th>
        <th><?= $biBoth('pdf.vat_amount') ?></th>
        <th><?= $biBoth('common.total') ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($saudiVatSummary as $row): ?>
        <tr>
          <td><?= pdfText($row['tax_code'], 'en') ?></td>
          <td><?= rtrim(rtrim(number_format($row['tax_rate'], 2), '0'), '.') ?>%</td>
          <td><?= number_format($row['taxable'], 2) ?></td>
          <td><?= number_format($row['vat'], 2) ?></td>
          <td><?= number_format($row['total'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

  <table class="saudi-totals-table" style="width:320px;<?= $rtl ? 'float:right;' : 'float:left;' ?>">
    <tr><td class="label"><?= $biBoth('pdf.total_excluding_vat') ?></td><td><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
    <?php if (!empty($discountAmount)): ?>
      <tr><td class="label"><?= $biBoth('pdf.discount') ?></td><td><?= number_format($discountAmount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($otherCharges)): ?>
      <tr><td class="label"><?= $biBoth('pdf.other_charges') ?></td><td><?= number_format($otherCharges, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($discountAmount) || !empty($otherCharges)): ?>
      <tr><td class="label"><?= $biBoth('pdf.total_taxable_amount') ?></td><td><?= number_format($taxableAmount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <?php if (isset($vatAmount)): ?>
      <tr><td class="label">VAT <?= $vatRate ?>% / <?= $Tboth('ضريبة القيمة المضافة') ?></td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td class="label"><?= $biBoth('pdf.total_including_vat') ?></td><td><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
    <?php if ($paidAmount !== null): ?>
      <tr><td class="label"><?= $biBoth('common.paid') ?></td><td><?= number_format($paidAmount, 2) ?> <?= $currency ?></td></tr>
      <tr><td class="label"><?= $biBoth('pdf.balance_due') ?></td><td><?= number_format($balanceDue, 2) ?> <?= $currency ?></td></tr>
    <?php endif; ?>
  </table>
  <div style="clear:both;"></div>

  <div class="saudi-words">Amount in words: <?= \App\Support\Pdf\NumberToWords::sar($total) ?></div>

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
