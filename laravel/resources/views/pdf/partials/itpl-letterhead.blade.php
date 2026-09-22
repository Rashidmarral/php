<?php
/**
 * 'letterhead' layout body — included from document-v2.blade.php (see that
 * file's own comment on why this system stays plain-PHP include()s).
 *
 * Prints onto an uploaded letterhead banner image spanning the page width;
 * falls back to a plain company-name/logo header when the company chose
 * this layout but hasn't uploaded an image yet, rather than leaving a
 * broken/empty space at the top of the page.
 */
$accent = $tpl->accent_color ?: '#16233f';
$showLogo = (bool) $tpl->show_logo;
$showPartyVat = (bool) $tpl->show_party_vat_number;
$showVatColumn = (bool) $tpl->show_vat_column;
$showUnitLabels = (bool) $tpl->show_unit_labels;

$primary = fn ($en, $ar = null) => ($primaryLocale === 'ar' && $ar) ? $ar : $en;
$secondary = fn ($ar = null) => ($showAr && $primaryLocale !== 'ar') ? $ar : null;
// See itpl-card.blade.php's identical comment: dompdf does not reverse a
// table's visual column order on its own for a `dir`/`direction: rtl`
// table, so table_direction only does anything once the cells themselves
// are reordered server-side.
$reorderCells = fn (array $cells): array => $tableDirection === 'rtl' ? array_reverse($cells) : $cells;
?>
<?php if (!empty($tpl->letterhead_path)): ?>
  <img src="<?= 'file://' . public_path($tpl->letterhead_path) ?>" class="itpl-lh-banner">
<?php else: ?>
  <!-- No letterhead image uploaded yet — graceful fallback to a plain
       company-name/logo header instead of leaving the page top empty. -->
  <table class="itpl-lh-fallback-header"><tr>
    <td style="width:70%">
      <?php if ($showLogo && !empty($companyLogo)): ?>
        <img src="<?= $companyLogo ?>" style="max-height:44px;max-width:130px;margin-bottom:6px;">
      <?php endif; ?>
      <div class="itpl-lh-fallback-name"><?= pdfText($primary($issuer['name'] ?? '', $companyNameAr ?: null), $primaryLocale) ?></div>
      <?php if ($secondary($companyNameAr ?: null)): ?>
        <div class="muted small ar-text"><?= pdfText($companyNameAr, 'ar') ?></div>
      <?php endif; ?>
      <?php foreach (($issuer['meta'] ?? []) as $line): ?>
        <div class="muted small"><?= pdfText($line, $primaryLocale) ?></div>
      <?php endforeach; ?>
    </td>
    <td style="width:30%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
      <?php if (!empty($qrCode)): ?><img src="<?= $qrCode ?>" width="72" height="72"><?php endif; ?>
    </td>
  </tr></table>
<?php endif; ?>

<div class="itpl-lh-title"><?= pdfText($docTypeLabel, $primaryLocale) ?></div>

<table class="itpl-lh-info-table"><tr>
  <td style="width:50%;vertical-align:top;">
    <table>
      <tr><td class="label"><?= $L($partyLabelKey) ?></td><td><?= pdfText($primary($billTo['name'] ?? '', $partyNameAr), $primaryLocale) ?></td></tr>
      <?php if ($showPartyVat && !empty($partyVat)): ?>
        <tr><td class="label"><?= $L('common.vat') ?></td><td><?= pdfText($partyVat, $primaryLocale) ?></td></tr>
      <?php endif; ?>
      <?php foreach (($billTo['meta'] ?? []) as $line): ?>
        <tr><td></td><td class="muted small"><?= pdfText($line, $primaryLocale) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </td>
  <td style="width:50%;vertical-align:top;">
    <table>
      <tr><td class="label"><?= $L('common.number') ?></td><td><?= pdfText((string) $docNumber, $primaryLocale) ?></td></tr>
      <tr><td class="label"><?= $L('common.date') ?></td><td><?= pdfText((string) $docDate, $primaryLocale) ?></td></tr>
      <?php if (!empty($validUntil)): ?>
        <tr><td class="label"><?= $L('pdf.valid_until') ?></td><td><?= pdfText((string) $validUntil, $primaryLocale) ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($status)): ?>
        <tr><td class="label"><?= $L('common.status') ?></td><td><?= pdfText((string) $status, $primaryLocale) ?></td></tr>
      <?php endif; ?>
    </table>
  </td>
</tr></table>

<table class="itpl-lh-items-table" dir="<?= $tableDirection ?>">
  <thead>
    <tr>
      <?php foreach ($reorderCells(array_filter([
        ['class' => '', 'style' => 'width:6%', 'html' => '#'],
        ['class' => '', 'style' => '', 'html' => $L('common.description')],
        ['class' => 'num', 'style' => '', 'html' => $L('common.qty')],
        ['class' => 'num', 'style' => '', 'html' => $L('common.unit_price')],
        $showVatColumn ? ['class' => 'num', 'style' => '', 'html' => $L('pdf.vat_amount')] : null,
        ['class' => 'num', 'style' => '', 'html' => $L('common.total')],
      ])) as $cell): ?>
        <th class="<?= $cell['class'] ?>" style="<?= $cell['style'] ?>"><?= $cell['html'] ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($itemRows as $index => $row): ?>
      <tr>
        <?php foreach ($reorderCells(array_filter([
          ['class' => '', 'html' => $index + 1],
          ['class' => '', 'html' => pdfText($row['description'], $primaryLocale)],
          ['class' => 'num', 'html' => rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') . ($showUnitLabels ? ' <span class="muted small">' . $L('common.unit') . '</span>' : '')],
          ['class' => 'num', 'html' => number_format($row['unit_price'], 2)],
          $showVatColumn ? ['class' => 'num', 'html' => number_format($row['vat'], 2)] : null,
          ['class' => 'num', 'html' => number_format($row['total'], 2)],
        ])) as $cell): ?>
          <td class="<?= $cell['class'] ?>"><?= $cell['html'] ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="itpl-lh-words"><?= $L('pdf.amount_in_words') ?>: <?= \App\Support\Pdf\NumberToWords::sar($total) ?></div>

<table class="itpl-lh-totals-table">
  <tr><td><?= $L('common.subtotal') ?></td><td class="num"><?= $currency ?> <?= number_format($subtotal, 2) ?></td></tr>
  <?php if (!empty($discountAmount)): ?>
    <tr><td><?= $L('pdf.discount') ?></td><td class="num">-<?= $currency ?> <?= number_format($discountAmount, 2) ?></td></tr>
  <?php endif; ?>
  <?php if (isset($vatAmount)): ?>
    <tr><td><?= $L('common.vat') ?></td><td class="num"><?= $currency ?> <?= number_format($vatAmount, 2) ?></td></tr>
  <?php endif; ?>
  <tr class="grand"><td><?= $L('common.total') ?></td><td class="num"><?= $currency ?> <?= number_format($total, 2) ?></td></tr>
</table>
<div class="clearfix"></div>

<?php if (!empty($company->bank_name ?? null)): ?>
  <!-- See itpl-card.blade.php's identical note: no per-company bank-account
       columns exist yet, so this stays a silent no-op until a later stage
       adds them — never the platform's OWN Setting::get('bank_name'). -->
  <div class="itpl-lh-bank">
    <h4><?= $L('pdf.bank_details') ?></h4>
    <div><?= $L('pdf.bank_name') ?>: <?= pdfText($company->bank_name, $primaryLocale) ?>
      <?php if (!empty($company->iban ?? null)): ?> &nbsp; <?= $L('common.iban') ?>: <?= pdfText($company->iban, $primaryLocale) ?><?php endif; ?>
      <?php if (!empty($company->account_number ?? null)): ?> &nbsp; <?= $L('pdf.account_number') ?>: <?= pdfText($company->account_number, $primaryLocale) ?><?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($notes)): ?>
  <div class="notes-box" style="margin-top:16px;clear:both;">
    <h4 class="muted small" style="text-transform:uppercase;"><?= $L('common.notes') ?></h4>
    <div><?= pdfText($notes, $primaryLocale) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($tpl->terms_en) || !empty($tpl->terms_ar)): ?>
  <div style="margin-top:10px;">
    <h4 class="muted small" style="text-transform:uppercase;"><?= $L('pdf.terms') ?></h4>
    <div class="muted small"><?= pdfText($primary($tpl->terms_en ?: '', $tpl->terms_ar), $primaryLocale) ?></div>
  </div>
<?php endif; ?>

<div class="itpl-lh-signature">
  <div class="line"></div>
  <div class="muted small" style="text-align:<?= $rtl ? 'left' : 'right' ?>;"><?= $L('pdf.authorized_signature') ?></div>
</div>

<div class="itpl-lh-footer"><?= pdfText((string) ($footerNote ?? ''), $primaryLocale) ?></div>
