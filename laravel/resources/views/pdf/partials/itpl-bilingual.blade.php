<?php
/**
 * 'bilingual' layout body — included from document-v2.blade.php (see that
 * file's own comment on why this system stays plain-PHP include()s).
 *
 * An upgrade of the existing 'saudi' bordered ZATCA template
 * (PdfTemplateStyles::saudiChrome() / document.blade.php's `$tpl ===
 * 'saudi'` branch), built fresh here under new `.itpl-bl-*` classes so the
 * OLD 6-preset system stays byte-for-byte unchanged. Adds per-row bilingual
 * (EN/AR) info-table labels the old saudi template didn't have (it was
 * only bilingual in its header/title/items, not its info rows), splits
 * taxable amount and VAT amount into their own item columns, and adds an
 * optional company-stamp slot (silently inert until a later stage adds
 * Company::stamp_path — Stage 1 didn't add that column, see this stage's
 * own task notes) plus the ZATCA cleared/reported badge.
 *
 * Respects language_mode even though the layout's whole identity is
 * "bilingual": when $showEn or $showAr is false, the dual EN|value|AR rows
 * collapse to a single-language row instead of showing an empty column.
 */
$accent = $tpl->accent_color ?: '#16233f';
$showLogo = (bool) $tpl->show_logo;
$showPartyVat = (bool) $tpl->show_party_vat_number;
$showVatColumn = (bool) $tpl->show_vat_column;

// pdf.doctype.<documentType> gives a genuine EN/AR title pair; a caller
// with no known documentType (shouldn't happen for the 8 real document
// types, but kept safe) falls back to the single already-localized
// $docType string on whichever side matches the current $lang.
$titleEn = $documentType ? tFor('pdf.doctype.' . $documentType, 'en') : ($rtl ? '' : $docType);
$titleAr = $documentType ? tFor('pdf.doctype.' . $documentType, 'ar') : ($rtl ? $docType : '');
$vatRateDisplay = rtrim(rtrim(number_format((float) $vatRate, 2), '0'), '.');

// Bilingual EN/AR header label, "EN / AR" respecting $showEn/$showAr.
$biLabel = function (string $key) use ($showEn, $showAr): string {
    $out = $showEn ? tFor($key, 'en') : '';
    if ($showEn && $showAr) {
        $out .= ' / ';
    }
    return $out . ($showAr ? tFor($key, 'ar') : '');
};
// See itpl-card.blade.php's identical comment: dompdf does not reverse a
// table's visual column order on its own for a `dir`/`direction: rtl`
// table, so table_direction only does anything once the cells themselves
// are reordered server-side.
$reorderCells = fn (array $cells): array => $tableDirection === 'rtl' ? array_reverse($cells) : $cells;
?>
<table class="itpl-bl-header"><tr>
  <?php if ($showEn): ?>
    <td style="width:<?= $showAr ? '35%' : '70%' ?>">
      <div class="itpl-bl-company-en"><?= pdfText($issuer['name'] ?? '', 'en') ?></div>
      <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta"><?= tFor('common.vat', 'en') ?>: <?= pdfText($company->vat_number, 'en') ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta"><?= tFor('pdf.cr_number', 'en') ?>: <?= pdfText($company->cr_number, 'en') ?></div><?php endif; ?>
    </td>
  <?php endif; ?>
  <?php if ($showLogo && !empty($companyLogo)): ?>
    <td style="width:30%;text-align:center;"><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"></td>
  <?php endif; ?>
  <?php if ($showAr): ?>
    <td style="width:<?= $showEn ? '35%' : '70%' ?>">
      <div class="itpl-bl-company-ar"><?= !empty($companyNameAr) ? pdfText($companyNameAr, 'ar') : e($issuer['name'] ?? '') ?></div>
      <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta ar"><?= tFor('common.vat', 'ar') ?>: <?= pdfText($company->vat_number, 'ar') ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta ar"><?= tFor('pdf.cr_number', 'ar') ?>: <?= pdfText($company->cr_number, 'ar') ?></div><?php endif; ?>
    </td>
  <?php endif; ?>
</tr></table>

<div class="itpl-bl-title-bar">
  <?php if ($showEn): ?><?= strtoupper(pdfText($titleEn, 'en')) ?><?php endif; ?>
  <?php if ($showEn && $showAr): ?>&nbsp;|&nbsp;<?php endif; ?>
  <?php if ($showAr): ?><span class="ar-text"><?= pdfText($titleAr, 'ar') ?></span><?php endif; ?>
</div>

<table class="itpl-bl-info-table"><tr>
  <?php if ($showEn): ?><td class="label-en"><?= tFor('common.number', 'en') ?></td><td class="value"><?= pdfText((string) $docNumber, 'en') ?></td><?php endif; ?>
  <?php if ($showAr): ?><td class="label-ar"><?= tFor('common.number', 'ar') ?></td><td class="value ar-text"><?= pdfText((string) $docNumber, 'ar') ?></td><?php endif; ?>
</tr><tr>
  <?php if ($showEn): ?><td class="label-en"><?= tFor('common.date', 'en') ?></td><td class="value"><?= pdfText((string) $docDate, 'en') ?></td><?php endif; ?>
  <?php if ($showAr): ?><td class="label-ar"><?= tFor('common.date', 'ar') ?></td><td class="value ar-text"><?= pdfText((string) $docDate, 'ar') ?></td><?php endif; ?>
</tr>
<?php if (!empty($status) || !empty($validUntil)): ?>
<tr>
  <?php if ($showEn): ?><td class="label-en"><?= !empty($status) ? tFor('common.status', 'en') : tFor('pdf.valid_until', 'en') ?></td><td class="value"><?= pdfText((string) ($status ?: $validUntil), 'en') ?></td><?php endif; ?>
  <?php if ($showAr): ?><td class="label-ar"><?= !empty($status) ? tFor('common.status', 'ar') : tFor('pdf.valid_until', 'ar') ?></td><td class="value ar-text"><?= pdfText((string) ($status ?: $validUntil), 'ar') ?></td><?php endif; ?>
</tr>
<?php endif; ?>
</table>

<?php if (!empty($billTo)): ?>
<table class="itpl-bl-info-table"><tr>
  <?php if ($showEn): ?><td class="label-en"><?= tFor($partyLabelKey, 'en') ?></td><td class="value" colspan="<?= $showAr ? 1 : 3 ?>"><?= pdfText($billTo['name'] ?? '', 'en') ?></td><?php endif; ?>
  <?php if ($showAr): ?><td class="label-ar"><?= tFor($partyLabelKey, 'ar') ?></td><td class="value ar-text" colspan="<?= $showEn ? 1 : 3 ?>"><?= !empty($partyNameAr) ? pdfText($partyNameAr, 'ar') : e($billTo['name'] ?? '') ?></td><?php endif; ?>
</tr>
<?php if ($showPartyVat && !empty($partyVat)): ?>
<tr>
  <?php if ($showEn): ?><td class="label-en"><?= tFor('common.vat', 'en') ?></td><td class="value"><?= pdfText($partyVat, 'en') ?></td><?php endif; ?>
  <?php if ($showAr): ?><td class="label-ar"><?= tFor('common.vat', 'ar') ?></td><td class="value ar-text"><?= pdfText($partyVat, 'ar') ?></td><?php endif; ?>
</tr>
<?php endif; ?>
</table>
<?php endif; ?>

<table class="itpl-bl-items-table" dir="<?= $tableDirection ?>">
  <thead>
    <tr>
      <?php foreach ($reorderCells(array_filter([
        ['class' => '', 'style' => 'width:6%', 'html' => '#'],
        ['class' => 'desc', 'style' => '', 'html' => $biLabel('common.description')],
        ['class' => '', 'style' => '', 'html' => $biLabel('common.qty')],
        ['class' => '', 'style' => '', 'html' => $biLabel('common.unit_price')],
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biLabel('pdf.taxable_amount')] : null,
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biLabel('pdf.vat_amount')] : null,
        ['class' => '', 'style' => '', 'html' => $biLabel('common.total')],
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
          ['class' => 'desc', 'html' => pdfText($row['description'], $primaryLocale)],
          ['class' => '', 'html' => rtrim(rtrim(number_format($row['qty'], 2), '0'), '.')],
          ['class' => '', 'html' => number_format($row['unit_price'], 2)],
          $showVatColumn ? ['class' => '', 'html' => number_format($row['taxable'], 2)] : null,
          $showVatColumn ? ['class' => '', 'html' => number_format($row['vat'], 2)] : null,
          ['class' => '', 'html' => number_format($row['total'], 2)],
        ])) as $cell): ?>
          <td class="<?= $cell['class'] ?>"><?= $cell['html'] ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<table class="itpl-bl-totals-table">
  <tr><td class="label"><?php if ($showEn): ?><?= tFor('common.subtotal', 'en') ?><?php endif; ?><?php if ($showEn && $showAr): ?> / <?php endif; ?><?php if ($showAr): ?><?= tFor('common.subtotal', 'ar') ?><?php endif; ?></td><td><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
  <?php if (!empty($discountAmount)): ?>
    <tr><td class="label"><?php if ($showEn): ?><?= tFor('pdf.discount', 'en') ?><?php endif; ?><?php if ($showEn && $showAr): ?> / <?php endif; ?><?php if ($showAr): ?><?= tFor('pdf.discount', 'ar') ?><?php endif; ?></td><td>-<?= number_format($discountAmount, 2) ?> <?= $currency ?></td></tr>
  <?php endif; ?>
  <?php if (isset($vatAmount)): ?>
    <tr><td class="label"><?php if ($showEn): ?><?= tFor('common.vat', 'en') ?> <?= $vatRateDisplay ?>%<?php endif; ?><?php if ($showEn && $showAr): ?> / <?php endif; ?><?php if ($showAr): ?><?= tFor('common.vat', 'ar') ?> <?= $vatRateDisplay ?>%<?php endif; ?></td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
  <?php endif; ?>
  <tr class="grand"><td class="label"><?php if ($showEn): ?><?= tFor('common.total', 'en') ?><?php endif; ?><?php if ($showEn && $showAr): ?> / <?php endif; ?><?php if ($showAr): ?><?= tFor('common.total', 'ar') ?><?php endif; ?></td><td><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
</table>
<div class="clearfix"></div>

<div class="itpl-bl-words"><?= tFor('pdf.amount_in_words', $primaryLocale) ?>: <?= \App\Support\Pdf\NumberToWords::sar($total) ?></div>

<?php if (!empty($notes)): ?>
  <div style="clear:both;margin-top:8px;font-size:9.5px;color:#444;">
    <strong><?= $L('common.notes') ?>:</strong> <?= pdfText($notes, $primaryLocale) ?>
  </div>
<?php endif; ?>

<table class="itpl-bl-sign-table"><tr>
  <?php if (!empty($qrCode)): ?>
    <td style="width:110px;vertical-align:top;">
      <?php if (!empty($zatcaStatus)): ?>
        <div class="itpl-bl-zatca-pill"><?= $zatcaStatus === 'cleared' ? tFor('pdf.zatca_cleared', $primaryLocale) : tFor('pdf.zatca_reported', $primaryLocale) ?></div><br>
      <?php endif; ?>
      <img src="<?= $qrCode ?>" width="90" height="90">
    </td>
  <?php endif; ?>
  <?php if (!empty($company->stamp_path ?? null)): ?>
    <!-- Stage 1 didn't add stamp_path — silent no-op until a later stage adds it. -->
    <td class="itpl-bl-stamp" style="width:110px;"><img src="<?= 'file://' . public_path($company->stamp_path) ?>" style="max-height:90px;max-width:90px;"></td>
  <?php endif; ?>
  <td style="width:50%"><?= $L('pdf.authorized_signature') ?>: ______________________</td>
</tr></table>

<div class="itpl-bl-footer"><?= pdfText((string) ($footerNote ?? ''), $primaryLocale) ?></div>
