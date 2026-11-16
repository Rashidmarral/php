<?php
/**
 * 'bilingual' layout body — included from document-v2.blade.php (see that
 * file's own comment on why this system stays plain-PHP include()s).
 *
 * Ported from Daftari's real `bilingual_classic` mPDF template
 * (daftari/resources/views/documents/print/pdf.blade.php, included
 * partials pdf-bilingual-header/pdf-payment-status-badge/pdf-bank-totals/
 * pdf-signature) rather than the from-scratch ZATCA-simplified-invoice
 * approximation this replaced. The old approximation boxed everything in
 * accent-colored borders (a full-grid header box, a solid filled title
 * bar, a fully gridded items table) — Daftari's real layout is far more
 * restrained: a thin top accent rule, an unboxed header, a centered title
 * under a single border-bottom rule, a colored payment-status pill, a
 * bordered info table with thin row separators (not a full grid), an
 * items table with row/column separator lines instead of a boxed grid,
 * and a two-column bank-details+totals footer block. `.itpl-bl-*` stays
 * its own class namespace (see InvoiceTemplateStyles::bilingualChrome()),
 * separate from PdfTemplateStyles::saudiChrome()'s `.saudi-*` classes, so
 * the OLD 6-preset system's saudi template is never touched here — and
 * payment-certificate-v2.blade.php's own 'bilingual' branch shares these
 * same classes/CSS for the same look on that document type.
 *
 * Respects language_mode even though the layout's whole identity is
 * "bilingual": when $showEn or $showAr is false, the dual EN|value|AR rows
 * collapse to a single-language row instead of showing an empty column.
 */
$accent = $tpl->accent_color ?: '#16233f';
$showLogo = (bool) $tpl->show_logo;
$showPartyVat = (bool) $tpl->show_party_vat_number;
$showVatColumn = (bool) $tpl->show_vat_column;
$vatRateDisplay = rtrim(rtrim(number_format((float) $vatRate, 2), '0'), '.');

// pdf.doctype.<documentType> gives a genuine EN/AR title pair; a caller
// with no known documentType (shouldn't happen for the 8 real document
// types, but kept safe) falls back to the single already-localized
// $docType string on whichever side matches the current $lang.
$titleEn = $documentType ? tFor('pdf.doctype.' . $documentType, 'en') : ($rtl ? '' : $docType);
$titleAr = $documentType ? tFor('pdf.doctype.' . $documentType, 'ar') : ($rtl ? $docType : '');

// Bilingual EN/AR header label, "EN / AR" respecting $showEn/$showAr — used
// for the totals-table row labels, exactly like Daftari's own $lbl() helper
// (which combines both languages onto the SAME line for those rows, unlike
// the info-table/items-table labels below, which get their own column/line).
$biLabel = function (string $key) use ($showEn, $showAr): string {
    $out = $showEn ? tFor($key, 'en') : '';
    if ($showEn && $showAr) {
        $out .= ' / ';
    }
    return $out . ($showAr ? tFor($key, 'ar') : '');
};
// The items-table header's stacked EN-line / smaller-AR-line pair (see
// .itpl-bl-items-table th .ar-text in InvoiceTemplateStyles::bilingualChrome()).
$biStack = function (string $key) use ($showEn, $showAr): string {
    $out = $showEn ? tFor($key, 'en') : '';
    if ($showAr) {
        $out .= '<span class="ar-text">' . tFor($key, 'ar') . '</span>';
    }
    return $out;
};
// One info-table row: an EN label cell, a single shared value cell (its
// content already bilingual-aware where the EN/AR text genuinely differs,
// e.g. a party's name_ar), and an AR label cell — mirrors Daftari's real
// 3-cell row shape (label-en | value | label-ar) instead of 4 separate
// label/value columns per language.
$infoRow = function (string $labelEn, string $labelAr, string $valueHtml) use ($showEn, $showAr): string {
    $cells = $showEn ? '<td class="label-en">' . $labelEn . '</td>' : '';
    $cells .= '<td class="value">' . $valueHtml . '</td>';
    $cells .= $showAr ? '<td class="label-ar ar-text">' . $labelAr . '</td>' : '';
    return '<tr>' . $cells . '</tr>';
};
// See itpl-card.blade.php's identical comment: dompdf does not reverse a
// table's visual column order on its own for a `dir`/`direction: rtl`
// table, so table_direction only does anything once the cells themselves
// are reordered server-side.
$reorderCells = fn (array $cells): array => $tableDirection === 'rtl' ? array_reverse($cells) : $cells;

// A colored payment-status pill (Daftari's pdf-payment-status-badge.blade.php),
// shown only for the handful of raw status values this app already has a
// bilingual common.* label for — any other value (issued/void/internal/
// accepted/declined/...) keeps rendering as the plain info-table row
// further down instead of risking an untranslated or blank pill.
$pillColors = [
    'paid' => ['bg' => '#d1fae5', 'color' => '#047857'],
    'unpaid' => ['bg' => '#fef3c7', 'color' => '#b45309'],
    'overdue' => ['bg' => '#fee2e2', 'color' => '#b91c1c'],
    'draft' => ['bg' => '#f1f5f9', 'color' => '#475569'],
];
$statusKey = strtolower((string) ($status ?? ''));
$statusPill = $pillColors[$statusKey] ?? null;
?>
<table class="itpl-bl-topbar"><tr><td></td></tr></table>

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
  <?php if ($showAr): ?><span class="ar-text"><?= pdfText($titleAr, 'ar') ?></span><?php endif; ?>
  <?php if ($showEn && $showAr): ?>&nbsp;&nbsp;<?php endif; ?>
  <?php if ($showEn): ?><?= pdfText($titleEn, 'en') ?><?php endif; ?>
</div>

<?php if ($statusPill): ?>
  <div style="text-align:center;margin-top:4px;">
    <span class="itpl-bl-status-pill" style="background:<?= $statusPill['bg'] ?>;color:<?= $statusPill['color'] ?>;"><?= $biLabel('common.' . $statusKey) ?></span>
  </div>
<?php endif; ?>

<table class="itpl-bl-info-table">
<?= $infoRow(
    tFor('common.number', 'en'),
    tFor('common.number', 'ar'),
    pdfText((string) $docNumber, $primaryLocale) . '&nbsp;|&nbsp;' . pdfText((string) $docDate, $primaryLocale)
) ?>
<?php if (!$statusPill && (!empty($status) || !empty($validUntil))): ?>
<?= $infoRow(
    !empty($status) ? tFor('common.status', 'en') : tFor('pdf.valid_until', 'en'),
    !empty($status) ? tFor('common.status', 'ar') : tFor('pdf.valid_until', 'ar'),
    pdfText((string) ($status ?: $validUntil), $primaryLocale)
) ?>
<?php endif; ?>
<?php if (!empty($billTo)): ?>
<?= $infoRow(
    tFor($partyLabelKey, 'en'),
    tFor($partyLabelKey, 'ar'),
    pdfText($billTo['name'] ?? '', $primaryLocale) . (!empty($partyNameAr) && $partyNameAr !== ($billTo['name'] ?? null) ? '<div class="ar-text">' . pdfText($partyNameAr, 'ar') . '</div>' : '')
) ?>
<?php if ($showPartyVat && !empty($partyVat)): ?>
<?= $infoRow(tFor('common.vat', 'en'), tFor('common.vat', 'ar'), pdfText($partyVat, $primaryLocale)) ?>
<?php endif; ?>
<?php endif; ?>
</table>

<table class="itpl-bl-items-table" dir="<?= $tableDirection ?>">
  <thead>
    <tr>
      <?php foreach ($reorderCells(array_filter([
        ['class' => '', 'style' => 'width:6%', 'html' => '#'],
        ['class' => 'desc', 'style' => '', 'html' => $biStack('common.description')],
        ['class' => '', 'style' => '', 'html' => $biStack('common.qty')],
        ['class' => '', 'style' => '', 'html' => $biStack('common.unit_price')],
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.taxable_amount')] : null,
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.vat_amount')] : null,
        ['class' => '', 'style' => '', 'html' => $biStack('common.total')],
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

<table class="itpl-bl-footer-wrap"><tr>
  <td style="width:<?= $rtl ? '45%' : '55%' ?>;vertical-align:top;">
    <?php if ($total > 0): ?>
      <div class="itpl-bl-words"><strong><?= $L('pdf.amount_in_words') ?>:</strong> <?= \App\Support\Pdf\NumberToWords::sar($total) ?></div>
    <?php endif; ?>
    <?php if (!empty($company->bank_name ?? null)): ?>
      <!-- $company->bank_name/iban/account_number: not real columns yet (this
           app has no per-company bank-account model) — same silent no-op
           pattern as itpl-card.blade.php's identical block. -->
      <div class="itpl-bl-bank">
        <strong><?= $L('pdf.bank_details') ?>:</strong><br>
        <?= $L('pdf.bank_name') ?>: <?= pdfText($company->bank_name, $primaryLocale) ?>
        <?php if (!empty($company->iban ?? null)): ?> &nbsp; <?= tFor('common.iban', $primaryLocale) ?>: <?= pdfText($company->iban, $primaryLocale) ?><?php endif; ?>
        <?php if (!empty($company->account_number ?? null)): ?> &nbsp; <?= $L('pdf.account_number') ?>: <?= pdfText($company->account_number, $primaryLocale) ?><?php endif; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($notes)): ?>
      <div style="margin-top:8px;font-size:9.5px;color:#444;">
        <strong><?= $L('common.notes') ?>:</strong> <?= pdfText($notes, $primaryLocale) ?>
      </div>
    <?php endif; ?>
  </td>
  <td style="width:<?= $rtl ? '55%' : '45%' ?>;vertical-align:top;">
    <table class="itpl-bl-totals-table" style="float:none;width:100%;">
      <tr><td class="label"><?= $biLabel('common.subtotal') ?></td><td><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
      <?php if (!empty($discountAmount)): ?>
        <tr><td class="label"><?= $biLabel('pdf.discount') ?></td><td>-<?= number_format($discountAmount, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <?php if (isset($vatAmount)): ?>
        <tr><td class="label"><?= $biLabel('common.vat') ?> <?= $vatRateDisplay ?>%</td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td class="label"><?= $biLabel('common.total') ?></td><td><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
    </table>
  </td>
</tr></table>

<?php if (!empty($qrCode) || !empty($company->stamp_path ?? null)): ?>
  <table style="margin-top:16px;"><tr>
    <td style="width:50%;vertical-align:top;">
      <?php if (!empty($qrCode)): ?>
        <?php if (!empty($zatcaStatus)): ?>
          <div class="itpl-bl-zatca-pill"><?= $zatcaStatus === 'cleared' ? tFor('pdf.zatca_cleared', $primaryLocale) : tFor('pdf.zatca_reported', $primaryLocale) ?></div><br>
        <?php endif; ?>
        <img src="<?= $qrCode ?>" width="90" height="90">
      <?php endif; ?>
    </td>
    <td style="width:50%;vertical-align:top;text-align:<?= $rtl ? 'left' : 'right' ?>;">
      <?php if (!empty($company->stamp_path ?? null)): ?>
        <!-- Stage 1 didn't add stamp_path — silent no-op until a later stage adds it. -->
        <img src="<?= 'file://' . public_path($company->stamp_path) ?>" style="max-height:90px;max-width:90px;">
      <?php endif; ?>
    </td>
  </tr></table>
<?php endif; ?>

<table class="itpl-bl-sign-table"><tr>
  <td style="width:50%;border-top:none;"></td>
  <td style="width:50%;text-align:<?= $rtl ? 'left' : 'right' ?>;"><?= $L('pdf.authorized_signature') ?></td>
</tr></table>

<div class="itpl-bl-footer"><?= pdfText((string) ($footerNote ?? ''), $primaryLocale) ?></div>
