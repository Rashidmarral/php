<?php
/**
 * 'card' layout body — included (plain PHP include(), not @include — see
 * document-v2.blade.php's own comment on why this whole system stays plain
 * PHP like the old document.blade.php/payment-certificate.blade.php rather
 * than Blade directives) from document-v2.blade.php, which already has
 * every variable used below in scope ($rtl, $tpl, $L, $itemRows, ...).
 *
 * Rounded-card foundation: a 2-column header (company info + a QR/ZATCA
 * card), a colored items-table header row, and a totals panel that is
 * either a solid accent-filled "boxed" panel or a plain bordered table —
 * see InvoiceTemplateStyles::cardUsesBoxedTotals() for which preset_key
 * values pick which.
 */
$accent = $tpl->accent_color ?: '#16233f';
$boxed = \App\Support\Pdf\InvoiceTemplateStyles::cardUsesBoxedTotals($tpl);
$showLogo = (bool) $tpl->show_logo;
$showPartyVat = (bool) $tpl->show_party_vat_number;
$showVatColumn = (bool) $tpl->show_vat_column;
$showUnitLabels = (bool) $tpl->show_unit_labels;

// English primary text with an Arabic secondary line underneath in
// 'bilingual' mode — never a side-by-side dual column (that visual
// treatment is what the 'bilingual' LAYOUT family is for); in
// 'english_only'/'arabic_only' mode there is no secondary line at all.
$primary = fn ($en, $ar = null) => ($primaryLocale === 'ar' && $ar) ? $ar : $en;
$secondary = fn ($ar = null) => ($showAr && $primaryLocale !== 'ar') ? $ar : null;

// New item-table columns/VAT-summary/address blocks only take up space once
// at least one real value exists for them — an invoice from before this
// feature (no item_code/unit/discount on any line, no structured
// seller/buyer address, no payment method) renders exactly as it always did.
$showItemCodeColumn = (bool) array_filter($itemRows, fn ($row) => !empty($row['item_code']));
$showUnitColumn = (bool) array_filter($itemRows, fn ($row) => !empty($row['unit']));
$showDiscountColumn = (bool) array_filter($itemRows, fn ($row) => ($row['discount'] ?? 0) > 0);
$sellerAddress = $sellerAddress ?? [];
$billToAddress = $billToAddress ?? [];
$paymentMethod = $paymentMethod ?? null;
$dateOfSupply = $dateOfSupply ?? null;
$otherCharges = (float) ($otherCharges ?? 0);
$taxableAmount = $taxableAmount ?? ((float) $total - (float) ($vatAmount ?? 0));
$paidAmount = $paidAmount ?? null;
$balanceDue = $balanceDue ?? null;

// table_direction is independent of the document's own language ($rtl above,
// which only reflects ?lang=) — it is a per-template choice of which way the
// items table itself reads. dompdf does not reverse a table's visual column
// order on its own for a `dir`/`direction: rtl` table (confirmed against
// dompdf directly: neither the dir attribute nor `direction`/`unicode-bidi`
// CSS moves a single column), so making this setting actually do anything
// means physically reordering the <th>/<td> cells server-side when it's set
// to 'rtl' — the `dir` attribute stays on the table too, for genuine BIDI
// text shaping inside each cell.
$reorderCells = fn (array $cells): array => $tableDirection === 'rtl' ? array_reverse($cells) : $cells;
?>
<div class="itpl-card-topbar"></div>

<table class="itpl-card-header"><tr>
  <td style="width:60%">
    <?php if ($showLogo && !empty($companyLogo)): ?>
      <img src="<?= $companyLogo ?>" style="max-height:48px;max-width:140px;margin-bottom:6px;">
    <?php endif; ?>
    <div class="itpl-card-company-name"><?= pdfText($primary($issuer['name'] ?? '', $companyNameAr ?: null), $primaryLocale) ?></div>
    <?php if ($secondary($companyNameAr ?: null)): ?>
      <div class="muted small ar-text"><?= pdfText($companyNameAr, 'ar') ?></div>
    <?php endif; ?>
    <?php
      // $issuer['meta'] already carries pre-formatted "VAT: ..."/"CR: ..."
      // lines from the calling controller (kept exactly as the OLD template
      // needs them) — filtered out here since this layout shows the
      // company's VAT/CR as its own dedicated badges below instead, using
      // $company directly rather than re-parsing those opaque strings.
      $genericMeta = array_filter($issuer['meta'] ?? [], fn ($line) => !preg_match('/^(VAT|CR)\s*:/i', (string) $line));
    ?>
    <?php foreach ($genericMeta as $line): ?>
      <div class="muted small"><?= pdfText($line, $primaryLocale) ?></div>
    <?php endforeach; ?>
    <?php if ($showPartyVat && !empty($company->vat_number)): ?>
      <span class="itpl-card-vat-badge"><?= $L('common.vat') ?> <?= pdfText($company->vat_number, $primaryLocale) ?></span>
    <?php endif; ?>
    <?php if (!empty($company->cr_number)): ?>
      <span class="itpl-card-vat-badge"><?= $L('pdf.cr_number') ?> <?= pdfText($company->cr_number, $primaryLocale) ?></span>
    <?php endif; ?>
    <?php foreach (\App\Support\Pdf\AddressFormatter::lines($sellerAddress, $primaryLocale) as $line): ?>
      <div class="muted small"><?= $line ?></div>
    <?php endforeach; ?>
  </td>
  <td style="width:40%;text-align:<?= $rtl ? 'left' : 'right' ?>;">
    <span class="itpl-card-doc-badge"><?= pdfText($docTypeLabel, $primaryLocale) ?></span>
    <div class="muted" style="margin-top:8px;">#<?= pdfText((string) $docNumber, $primaryLocale) ?></div>
    <div class="muted small"><?= pdfText((string) $docDate, $primaryLocale) ?></div>
    <?php if (!empty($validUntil)): ?>
      <div class="muted small"><?= $L('pdf.valid_until') ?>: <?= pdfText((string) $validUntil, $primaryLocale) ?></div>
    <?php endif; ?>
    <?php if (!empty($paymentMethod)): ?>
      <div class="muted small"><?= $L('pdf.payment_method') ?>: <?= $L('pdf.payment_method.' . $paymentMethod) ?></div>
    <?php endif; ?>
    <?php if (!empty($dateOfSupply)): ?>
      <div class="muted small"><?= $L('pdf.date_of_supply') ?>: <?= pdfText((string) $dateOfSupply, $primaryLocale) ?></div>
    <?php endif; ?>
    <?php if (!empty($status)): ?>
      <div class="muted small" style="margin-top:4px;"><?= pdfText((string) $status, $primaryLocale) ?></div>
    <?php endif; ?>
  </td>
</tr></table>

<table style="margin-top:16px;"><tr>
  <td style="width:50%;vertical-align:top;padding-<?= $rtl ? 'left' : 'right' ?>:8px;">
    <?php if (!empty($billTo)): ?>
      <div class="itpl-card-box">
        <h4><?= $L($partyLabelKey) ?></h4>
        <div style="font-weight:700;"><?= pdfText($primary($billTo['name'] ?? '', $partyNameAr), $primaryLocale) ?></div>
        <?php if ($secondary($partyNameAr)): ?>
          <div class="muted small ar-text"><?= pdfText($partyNameAr, 'ar') ?></div>
        <?php endif; ?>
        <?php if ($showPartyVat && !empty($partyVat)): ?>
          <div class="muted small"><?= $L('common.vat') ?>: <?= pdfText($partyVat, $primaryLocale) ?></div>
        <?php endif; ?>
        <?php foreach (($billTo['meta'] ?? []) as $line): ?>
          <div class="muted small"><?= pdfText($line, $primaryLocale) ?></div>
        <?php endforeach; ?>
        <?php foreach (\App\Support\Pdf\AddressFormatter::lines($billToAddress, $primaryLocale) as $line): ?>
          <div class="muted small"><?= $line ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </td>
  <td style="width:50%;vertical-align:top;padding-<?= $rtl ? 'right' : 'left' ?>:8px;">
    <?php if (!empty($qrCode)): ?>
      <div class="itpl-card-box" style="text-align:center;">
        <?php if (!empty($zatcaStatus)): ?>
          <div class="itpl-zatca-pill"><?= $zatcaStatus === 'cleared' ? $L('pdf.zatca_cleared') : $L('pdf.zatca_reported') ?></div>
        <?php endif; ?>
        <div><img src="<?= $qrCode ?>" width="96" height="96"></div>
        <div class="muted small"><?= $L('pdf.scan_to_verify') ?></div>
      </div>
    <?php endif; ?>
  </td>
</tr></table>

<div class="itpl-table-card">
  <table class="itpl-items-table" dir="<?= $tableDirection ?>">
    <thead>
      <tr>
        <?php foreach ($reorderCells(array_filter([
          $showItemCodeColumn ? ['class' => '', 'html' => $L('pdf.item_code')] : null,
          ['class' => '', 'html' => $L('common.description')],
          $showUnitColumn ? ['class' => '', 'html' => $L('common.unit')] : null,
          ['class' => 'num', 'html' => $L('common.qty')],
          ['class' => 'num', 'html' => $L('common.unit_price')],
          $showDiscountColumn ? ['class' => 'num', 'html' => $L('pdf.discount')] : null,
          $showVatColumn ? ['class' => 'num', 'html' => $L('pdf.taxable_amount')] : null,
          $showVatColumn ? ['class' => 'num', 'html' => $L('pdf.tax_rate')] : null,
          $showVatColumn ? ['class' => 'num', 'html' => $L('pdf.tax_code')] : null,
          $showVatColumn ? ['class' => 'num', 'html' => $L('pdf.vat_amount')] : null,
          ['class' => 'num', 'html' => $L('common.total')],
        ])) as $cell): ?>
          <th class="<?= $cell['class'] ?>"><?= $cell['html'] ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($itemRows as $row): ?>
        <tr>
          <?php foreach ($reorderCells(array_filter([
            $showItemCodeColumn ? ['class' => '', 'html' => pdfText((string) ($row['item_code'] ?? ''), $primaryLocale)] : null,
            ['class' => '', 'html' => pdfText($row['description'], $primaryLocale)],
            $showUnitColumn ? ['class' => '', 'html' => pdfText((string) ($row['unit'] ?? ''), $primaryLocale)] : null,
            ['class' => 'num', 'html' => rtrim(rtrim(number_format($row['qty'], 2), '0'), '.') . ($showUnitLabels ? ' <span class="muted small">' . $L('common.unit') . '</span>' : '')],
            ['class' => 'num', 'html' => number_format($row['unit_price'], 2)],
            $showDiscountColumn ? ['class' => 'num', 'html' => $row['discount'] > 0 ? number_format($row['discount'], 2) : '—'] : null,
            $showVatColumn ? ['class' => 'num', 'html' => number_format($row['taxable'], 2)] : null,
            $showVatColumn ? ['class' => 'num', 'html' => rtrim(rtrim(number_format($row['tax_rate'], 2), '0'), '.') . '%'] : null,
            $showVatColumn ? ['class' => 'num', 'html' => pdfText($row['tax_code'], $primaryLocale)] : null,
            $showVatColumn ? ['class' => 'num', 'html' => number_format($row['vat'], 2)] : null,
            ['class' => 'num', 'html' => number_format($row['total'], 2)],
          ])) as $cell): ?>
            <td class="<?= $cell['class'] ?>"><?= $cell['html'] ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (!empty($vatSummaryRows)): ?>
<div class="itpl-vat-summary-wrap">
  <h4 class="muted small" style="text-transform:uppercase;"><?= $L('pdf.vat_summary') ?></h4>
  <table class="itpl-vat-summary-table" dir="<?= $tableDirection ?>">
    <thead>
      <tr>
        <?php foreach ($reorderCells([$L('pdf.tax_code'), $L('pdf.tax_rate'), $L('pdf.taxable_amount'), $L('pdf.vat_amount'), $L('common.total')]) as $cellHtml): ?>
          <th class="num"><?= $cellHtml ?></th>
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
            <td class="num"><?= $cellHtml ?></td>
          <?php endforeach; ?>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="itpl-totals-wrap">
  <table class="itpl-totals-box <?= $boxed ? 'boxed' : 'plain' ?>">
    <tr><td><?= $L('pdf.total_excluding_vat') ?></td><td class="num"><?= $currency ?> <?= number_format($subtotal, 2) ?></td></tr>
    <?php if (!empty($discountAmount)): ?>
      <tr><td><?= $L('pdf.discount') ?><?php if (!empty($discountPercent)): ?> (<?= rtrim(rtrim(number_format($discountPercent, 2), '0'), '.') ?>%)<?php endif; ?></td><td class="num">-<?= $currency ?> <?= number_format($discountAmount, 2) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($otherCharges)): ?>
      <tr><td><?= $L('pdf.other_charges') ?></td><td class="num">+<?= $currency ?> <?= number_format($otherCharges, 2) ?></td></tr>
    <?php endif; ?>
    <?php if (!empty($discountAmount) || !empty($otherCharges)): ?>
      <tr><td><?= $L('pdf.total_taxable_amount') ?></td><td class="num"><?= $currency ?> <?= number_format($taxableAmount, 2) ?></td></tr>
    <?php endif; ?>
    <?php if (isset($vatAmount)): ?>
      <tr><td><?= $L('pdf.total_vat') ?> (<?= rtrim(rtrim(number_format((float) $vatRate, 2), '0'), '.') ?>%)</td><td class="num"><?= $currency ?> <?= number_format($vatAmount, 2) ?></td></tr>
    <?php endif; ?>
    <tr class="grand"><td><?= $L('pdf.total_including_vat') ?></td><td class="num"><?= $currency ?> <?= number_format($total, 2) ?></td></tr>
    <?php if ($paidAmount !== null): ?>
      <tr><td><?= $L('common.paid') ?></td><td class="num"><?= $currency ?> <?= number_format($paidAmount, 2) ?></td></tr>
      <tr><td><?= $L('pdf.balance_due') ?></td><td class="num"><?= $currency ?> <?= number_format($balanceDue, 2) ?></td></tr>
    <?php endif; ?>
  </table>
</div>
<div class="clearfix"></div>

<?php if (!empty($company->bank_name ?? null)): ?>
  <!-- $company->bank_name/iban/account_number: not real columns yet (this
       app has no per-company bank-account model — see Setting::get('bank_name')
       etc., which is the PLATFORM's OWN bank details for subscription
       billing, not a company's bank info to show ITS clients). Left as a
       silent no-op conditional, same pattern as the stamp_path slot below,
       so it lights up automatically once a later stage adds these fields
       instead of wrongly reusing the platform's own bank details here. -->
  <div class="itpl-bank-card itpl-card-box">
    <h4><?= $L('pdf.bank_details') ?></h4>
    <div class="muted small"><?= $L('pdf.bank_name') ?>: <?= pdfText($company->bank_name, $primaryLocale) ?></div>
    <?php if (!empty($company->iban ?? null)): ?><div class="muted small"><?= $L('common.iban') ?>: <?= pdfText($company->iban, $primaryLocale) ?></div><?php endif; ?>
    <?php if (!empty($company->account_number ?? null)): ?><div class="muted small"><?= $L('pdf.account_number') ?>: <?= pdfText($company->account_number, $primaryLocale) ?></div><?php endif; ?>
  </div>
<?php endif; ?>

<?php if (!empty($notes)): ?>
  <div class="notes-box" style="margin-top:18px;clear:both;">
    <h4 class="muted small" style="text-transform:uppercase;"><?= $L('common.notes') ?></h4>
    <div><?= pdfText($notes, $primaryLocale) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($tpl->notes_en) || !empty($tpl->notes_ar)): ?>
  <div style="margin-top:8px;clear:both;">
    <div class="muted small"><?= pdfText($primary($tpl->notes_en ?: '', $tpl->notes_ar), $primaryLocale) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($tpl->terms_en) || !empty($tpl->terms_ar)): ?>
  <div style="margin-top:10px;">
    <h4 class="muted small" style="text-transform:uppercase;"><?= $L('pdf.terms') ?></h4>
    <div class="muted small"><?= pdfText($primary($tpl->terms_en ?: '', $tpl->terms_ar), $primaryLocale) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($company->stamp_path ?? null)): ?>
  <!-- Stage 1 didn't add a stamp_path column — this stays a silent no-op
       until a later stage adds it, exactly as this stage's task describes. -->
  <div style="margin-top:16px;text-align:<?= $rtl ? 'left' : 'right' ?>;">
    <img src="<?= 'file://' . public_path($company->stamp_path) ?>" style="max-height:90px;max-width:90px;">
  </div>
<?php endif; ?>

<div class="itpl-signature">
  <div class="line"></div>
  <div class="muted small" style="text-align:<?= $rtl ? 'left' : 'right' ?>;"><?= $L('pdf.authorized_signature') ?></div>
</div>

<div class="itpl-footer-note"><?= pdfText((string) ($footerNote ?? ''), $primaryLocale) ?></div>
