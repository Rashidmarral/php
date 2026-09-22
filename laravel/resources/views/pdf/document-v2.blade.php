<?php
/**
 * Stage 2's per-company, per-document-type template renderer — used ONLY
 * when Company::activeInvoiceTemplateFor($documentType) returned a real
 * App\Models\InvoiceTemplate row (passed here as $invoiceTemplate). Every
 * document-PDF controller falls back to the original resources/views/pdf/
 * document.blade.php + Controller::streamPdf() path whenever that call
 * returns null (the common case for every company right after Stage 1),
 * so this file adds a new rendering path rather than replacing the old one.
 *
 * Branches on $invoiceTemplate->layout into the 3 structural families the
 * invoice_templates migration documents (card / bilingual / letterhead),
 * each implemented as its own partial under resources/views/pdf/partials/.
 * CSS lives in App\Support\Pdf\InvoiceTemplateStyles, a sibling to the old
 * system's PdfTemplateStyles.
 *
 * Expects the same flat data shape InvoiceController::pdf() (etc.) already
 * builds for the OLD document.blade.php — lang, currency, docType,
 * docNumber, docDate, validUntil, status, issuer, companyNameAr,
 * companyLogo, billTo, items, subtotal, discountPercent, discountAmount,
 * vatRate, vatAmount, total, qrCode, footerNote, notes — plus these new
 * v2-only keys a controller adds once it has resolved a real template:
 *   invoiceTemplate   The InvoiceTemplate row itself (required).
 *   company           The full Company model (for vat_number/cr_number/
 *                      address/stamp_path — richer than $issuer's
 *                      pre-flattened meta lines).
 *   documentType      One of InvoiceTemplate's document_type values —
 *                      reused here to look up a bilingual EN/AR document
 *                      title via pdf.doctype.<type> instead of the single-
 *                      language $docType string the old template used.
 *   partyLabelKey     'pdf.bill_to' (default) or 'pdf.supplier' for a
 *                      purchase order's vendor.
 *   partyNameAr       The client/supplier's Arabic name, if any.
 *   partyVat          The client's VAT number, if any (suppliers don't
 *                      carry one in this app).
 *   zatcaStatus       'cleared' | 'reported' | null — only invoices/credit
 *                      notes/debit notes carry this.
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
$partyLabelKey = $partyLabelKey ?? 'pdf.bill_to';
$documentType = $documentType ?? null;
$zatcaStatus = $zatcaStatus ?? null;
$partyNameAr = $partyNameAr ?? null;
$partyVat = $partyVat ?? null;
$notes = $notes ?? null;
$companyNameAr = $companyNameAr ?? '';
$companyLogo = $companyLogo ?? null;
$billTo = $billTo ?? null;
$status = $status ?? null;
$validUntil = $validUntil ?? null;
$qrCode = $qrCode ?? null;
$footerNote = $footerNote ?? '';

// A locale-explicit translate — 'pdf.*'/'common.*' keys via tFor() rather
// than t(), because a bilingual document's label language is a property of
// the layout/language_mode, never the current viewer's own app locale.
$L = fn (string $key) => tFor($key, $primaryLocale);

// The document-type title shown in the card/letterhead layouts' badge —
// $docType itself was already localized by the calling controller to
// whichever language the REQUEST asked for (?lang=), which is a different
// axis from this template's own language_mode. When $documentType is known
// (every real controller passes it), pdf.doctype.<type> gives a genuine
// title in $primaryLocale instead, so an arabic_only template still shows
// an Arabic title even for an ?lang=en request, and vice versa.
$docTypeLabel = $documentType ? tFor('pdf.doctype.' . $documentType, $primaryLocale) : $docType;

// Per-line taxable amount / VAT amount, split into their own columns for
// the 'card' (when show_vat_column is on) and 'bilingual' layouts — a real
// Saudi tax-invoice-format improvement over the old single "VAT" total.
// Items only carry a document-level $vatRate (no per-line rate), so every
// line is taxed at that same rate; this still yields two genuinely
// different figures per line (taxable amount vs the VAT charged on it),
// not the same value shown twice.
$rate = (float) ($vatRate ?? 0);
$itemRows = array_map(function ($item) use ($rate) {
    $qty = (float) ($item['qty'] ?? 0);
    $unitPrice = (float) ($item['unit_price'] ?? 0);
    $taxable = round($qty * $unitPrice, 2);
    return [
        'description' => (string) ($item['description'] ?? ''),
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'taxable' => $taxable,
        'vat' => round($taxable * $rate / 100, 2),
        'total' => (float) ($item['total'] ?? $taxable),
    ];
}, $items ?? []);
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
</style>
</head>
<body>
<div class="itpl-doc">
<?php
// resource_path(), not __DIR__: Blade compiles this file into a cache
// PHP file under storage/framework/views/, so __DIR__ at runtime would
// resolve to THAT directory, not this file's own resources/views/pdf/
// location.
$partialsDir = resource_path('views/pdf/partials');
?>
<?php if ($layout === 'bilingual'): ?>
  <?php include $partialsDir . '/itpl-bilingual.blade.php'; ?>
<?php elseif ($layout === 'letterhead'): ?>
  <?php include $partialsDir . '/itpl-letterhead.blade.php'; ?>
<?php else: ?>
  <?php include $partialsDir . '/itpl-card.blade.php'; ?>
<?php endif; ?>
</div>
</body>
</html>
