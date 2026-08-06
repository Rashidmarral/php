<?php
use App\Core\View;

$rtl = $lang === 'ar';
$tpl = in_array($template, ['classic', 'minimal'], true) ? $template : 'modern';
$T = fn($v) => View::pdfText($v, $lang);
$currency = $currency ?? 'SAR';
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
</style>
</head>
<body class="tpl-<?= $tpl ?>">

<?php if ($tpl === 'modern'): ?>
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

<div class="footer-note"><?= $T($footerNote ?? '') ?></div>

</body>
</html>
