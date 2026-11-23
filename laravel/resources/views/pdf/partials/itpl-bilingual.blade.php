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

// A locale-explicit translate routed through pdfText(), so an Arabic label
// gets the same glyph-shaping/joining its data values already get — tFor()
// on its own returns plain logical-order Arabic, which dompdf renders as
// disconnected, unjoined letters instead of proper cursive script. Every
// tFor() call in this file (below and inside $biLabel/$biStack) goes
// through this instead of calling tFor() directly.
$T = fn (string $key, string $locale) => pdfText(tFor($key, $locale), $locale);

// Bilingual EN/AR header label, "EN / AR" respecting $showEn/$showAr — used
// for the totals-table row labels, exactly like Daftari's own $lbl() helper
// (which combines both languages onto the SAME line for those rows, unlike
// the info-table/items-table labels below, which get their own column/line).
$biLabel = function (string $key) use ($showEn, $showAr, $T): string {
    $out = $showEn ? $T($key, 'en') : '';
    if ($showEn && $showAr) {
        $out .= ' / ';
    }
    return $out . ($showAr ? $T($key, 'ar') : '');
};
// The items-table header's stacked EN-line / smaller-AR-line pair (see
// .itpl-bl-items-table th .ar-text in InvoiceTemplateStyles::bilingualChrome()).
$biStack = function (string $key) use ($showEn, $showAr, $T): string {
    $out = $showEn ? $T($key, 'en') : '';
    if ($showAr) {
        $out .= '<span class="ar-text">' . $T($key, 'ar') . '</span>';
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

// New columns only take up space in the items table once at least one real
// line actually carries a value for them — an invoice created before this
// feature (no item_code/unit/discount on any line) renders the exact same
// column set it always did.
$showItemCodeColumn = (bool) array_filter($itemRows, fn ($row) => !empty($row['item_code']));
$showUnitColumn = (bool) array_filter($itemRows, fn ($row) => !empty($row['unit']));
$showDiscountColumn = (bool) array_filter($itemRows, fn ($row) => ($row['discount'] ?? 0) > 0);
$sellerAddress = $sellerAddress ?? [];
$billToAddress = $billToAddress ?? [];
$billToAddressLines = \App\Support\Pdf\AddressFormatter::lines($billToAddress, $primaryLocale);
// A secondary Arabic address block under the primary-language one, same
// convention as $partyNameAr's own secondary <div class="ar-text"> below —
// only shown when the layout is genuinely bilingual and the primary
// language isn't already Arabic (which would make it a duplicate).
$billToAddressLinesAr = ($showAr && $primaryLocale !== 'ar') ? \App\Support\Pdf\AddressFormatter::lines($billToAddress, 'ar') : [];
$paymentMethod = $paymentMethod ?? null;
$dateOfSupply = $dateOfSupply ?? null;
$otherCharges = (float) ($otherCharges ?? 0);
$taxableAmount = $taxableAmount ?? ((float) $total - (float) ($vatAmount ?? 0));
$paidAmount = $paidAmount ?? null;
$balanceDue = $balanceDue ?? null;
?>
<table class="itpl-bl-topbar"><tr><td></td></tr></table>

<table class="itpl-bl-header"><tr>
  <?php if ($showEn): ?>
    <td style="width:<?= $showAr ? '35%' : '70%' ?>">
      <div class="itpl-bl-company-en"><?= pdfText($issuer['name'] ?? '', 'en') ?></div>
      <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta"><?= $T('common.vat', 'en') ?>: <?= pdfText($company->vat_number, 'en') ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta"><?= $T('pdf.cr_number', 'en') ?>: <?= pdfText($company->cr_number, 'en') ?></div><?php endif; ?>
      <?php foreach (\App\Support\Pdf\AddressFormatter::lines($sellerAddress, 'en') as $line): ?><div class="itpl-bl-meta"><?= $line ?></div><?php endforeach; ?>
    </td>
  <?php endif; ?>
  <?php if ($showLogo && !empty($companyLogo)): ?>
    <td style="width:30%;text-align:center;"><img src="<?= $companyLogo ?>" style="max-height:50px;max-width:120px;"></td>
  <?php endif; ?>
  <?php if ($showAr): ?>
    <td style="width:<?= $showEn ? '35%' : '70%' ?>">
      <div class="itpl-bl-company-ar"><?= !empty($companyNameAr) ? pdfText($companyNameAr, 'ar') : e($issuer['name'] ?? '') ?></div>
      <?php if ($showPartyVat && !empty($company->vat_number)): ?><div class="itpl-bl-meta ar"><?= $T('common.vat', 'ar') ?>: <?= pdfText($company->vat_number, 'ar') ?></div><?php endif; ?>
      <?php if (!empty($company->cr_number)): ?><div class="itpl-bl-meta ar"><?= $T('pdf.cr_number', 'ar') ?>: <?= pdfText($company->cr_number, 'ar') ?></div><?php endif; ?>
      <?php foreach (\App\Support\Pdf\AddressFormatter::lines($sellerAddress, 'ar') as $line): ?><div class="itpl-bl-meta ar"><?= $line ?></div><?php endforeach; ?>
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
    $T('common.number', 'en'),
    $T('common.number', 'ar'),
    pdfText((string) $docNumber, $primaryLocale) . '&nbsp;|&nbsp;' . pdfText((string) $docDate, $primaryLocale)
) ?>
<?php if (!$statusPill && (!empty($status) || !empty($validUntil))): ?>
<?= $infoRow(
    !empty($status) ? $T('common.status', 'en') : $T('pdf.valid_until', 'en'),
    !empty($status) ? $T('common.status', 'ar') : $T('pdf.valid_until', 'ar'),
    pdfText((string) ($status ?: $validUntil), $primaryLocale)
) ?>
<?php endif; ?>
<?php if (!empty($billTo)): ?>
<?= $infoRow(
    $T($partyLabelKey, 'en'),
    $T($partyLabelKey, 'ar'),
    pdfText($billTo['name'] ?? '', $primaryLocale)
    . (!empty($partyNameAr) && $partyNameAr !== ($billTo['name'] ?? null) ? '<div class="ar-text">' . pdfText($partyNameAr, 'ar') . '</div>' : '')
    . ($billToAddressLines ? '<div class="small">' . implode('<br>', $billToAddressLines) . '</div>' : '')
    . ($billToAddressLinesAr ? '<div class="small ar-text">' . implode('<br>', $billToAddressLinesAr) . '</div>' : '')
) ?>
<?php if ($showPartyVat && !empty($partyVat)): ?>
<?= $infoRow($T('common.vat', 'en'), $T('common.vat', 'ar'), pdfText($partyVat, $primaryLocale)) ?>
<?php endif; ?>
<?php endif; ?>
<?php if (!empty($paymentMethod)): ?>
<?= $infoRow($T('pdf.payment_method', 'en'), $T('pdf.payment_method', 'ar'), $T('pdf.payment_method.' . $paymentMethod, $primaryLocale)) ?>
<?php endif; ?>
<?php if (!empty($dateOfSupply)): ?>
<?= $infoRow($T('pdf.date_of_supply', 'en'), $T('pdf.date_of_supply', 'ar'), pdfText((string) $dateOfSupply, $primaryLocale)) ?>
<?php endif; ?>
</table>

<table class="itpl-bl-items-table" dir="<?= $tableDirection ?>">
  <thead>
    <tr>
      <?php foreach ($reorderCells(array_filter([
        ['class' => '', 'style' => 'width:5%', 'html' => '#'],
        $showItemCodeColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.item_code')] : null,
        ['class' => 'desc', 'style' => '', 'html' => $biStack('common.description')],
        $showUnitColumn ? ['class' => '', 'style' => '', 'html' => $biStack('common.unit')] : null,
        ['class' => '', 'style' => '', 'html' => $biStack('common.qty')],
        ['class' => '', 'style' => '', 'html' => $biStack('common.unit_price')],
        $showDiscountColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.discount')] : null,
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.taxable_amount')] : null,
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.tax_rate')] : null,
        $showVatColumn ? ['class' => '', 'style' => '', 'html' => $biStack('pdf.tax_code')] : null,
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
          $showItemCodeColumn ? ['class' => '', 'html' => pdfText((string) ($row['item_code'] ?? ''), $primaryLocale)] : null,
          ['class' => 'desc', 'html' => pdfText($row['description'], $primaryLocale)],
          $showUnitColumn ? ['class' => '', 'html' => pdfText((string) ($row['unit'] ?? ''), $primaryLocale)] : null,
          ['class' => '', 'html' => rtrim(rtrim(number_format($row['qty'], 2), '0'), '.')],
          ['class' => '', 'html' => number_format($row['unit_price'], 2)],
          $showDiscountColumn ? ['class' => '', 'html' => $row['discount'] > 0 ? number_format($row['discount'], 2) : '—'] : null,
          $showVatColumn ? ['class' => '', 'html' => number_format($row['taxable'], 2)] : null,
          $showVatColumn ? ['class' => '', 'html' => rtrim(rtrim(number_format($row['tax_rate'], 2), '0'), '.') . '%'] : null,
          $showVatColumn ? ['class' => '', 'html' => pdfText($row['tax_code'], $primaryLocale)] : null,
          $showVatColumn ? ['class' => '', 'html' => number_format($row['vat'], 2)] : null,
          ['class' => '', 'html' => number_format($row['total'], 2)],
        ])) as $cell): ?>
          <td class="<?= $cell['class'] ?>"><?= $cell['html'] ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<?php if (!empty($vatSummaryRows)): ?>
<div class="itpl-bl-vat-summary-title"><?= $biLabel('pdf.vat_summary') ?></div>
<table class="itpl-bl-vat-summary-table" dir="<?= $tableDirection ?>">
  <thead>
    <tr>
      <?php foreach ($reorderCells([
        $biStack('pdf.tax_code'),
        $biStack('pdf.tax_rate'),
        $biStack('pdf.taxable_amount'),
        $biStack('pdf.vat_amount'),
        $biStack('common.total'),
      ]) as $cellHtml): ?>
        <th><?= $cellHtml ?></th>
      <?php endforeach; ?>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($vatSummaryRows as $row): ?>
      <tr>
        <?php foreach ($reorderCells([
          pdfText($row['tax_code'], $primaryLocale),
          rtrim(rtrim(number_format($row['tax_rate'], 2), '0'), '.') . '%',
          number_format($row['taxable'], 2),
          number_format($row['vat'], 2),
          number_format($row['total'], 2),
        ]) as $cellHtml): ?>
          <td><?= $cellHtml ?></td>
        <?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

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
        <?php if (!empty($company->iban ?? null)): ?> &nbsp; <?= $T('common.iban', $primaryLocale) ?>: <?= pdfText($company->iban, $primaryLocale) ?><?php endif; ?>
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
      <tr><td class="label"><?= $biLabel('pdf.total_excluding_vat') ?></td><td><?= number_format($subtotal, 2) ?> <?= $currency ?></td></tr>
      <?php if (!empty($discountAmount)): ?>
        <tr><td class="label"><?= $biLabel('pdf.discount') ?></td><td>-<?= number_format($discountAmount, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($otherCharges)): ?>
        <tr><td class="label"><?= $biLabel('pdf.other_charges') ?></td><td>+<?= number_format($otherCharges, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <?php if (!empty($discountAmount) || !empty($otherCharges)): ?>
        <tr><td class="label"><?= $biLabel('pdf.total_taxable_amount') ?></td><td><?= number_format($taxableAmount, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <?php if (isset($vatAmount)): ?>
        <tr><td class="label"><?= $biLabel('pdf.total_vat') ?> <?= $vatRateDisplay ?>%</td><td><?= number_format($vatAmount, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
      <tr class="grand"><td class="label"><?= $biLabel('pdf.total_including_vat') ?></td><td><?= number_format($total, 2) ?> <?= $currency ?></td></tr>
      <?php if ($paidAmount !== null): ?>
        <tr><td class="label"><?= $biLabel('common.paid') ?></td><td><?= number_format($paidAmount, 2) ?> <?= $currency ?></td></tr>
        <tr><td class="label"><?= $biLabel('pdf.balance_due') ?></td><td><?= number_format($balanceDue, 2) ?> <?= $currency ?></td></tr>
      <?php endif; ?>
    </table>
  </td>
</tr></table>

<?php if (!empty($qrCode) || !empty($company->stamp_path ?? null)): ?>
  <table style="margin-top:16px;"><tr>
    <td style="width:50%;vertical-align:top;">
      <?php if (!empty($qrCode)): ?>
        <?php if (!empty($zatcaStatus)): ?>
          <div class="itpl-bl-zatca-pill"><?= $zatcaStatus === 'cleared' ? $T('pdf.zatca_cleared', $primaryLocale) : $T('pdf.zatca_reported', $primaryLocale) ?></div><br>
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
