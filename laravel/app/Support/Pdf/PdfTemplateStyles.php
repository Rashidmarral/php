<?php

namespace App\Support\Pdf;

/**
 * The shared "chrome" CSS for the app's multi-template PDF look-and-feel system —
 * the base element rules (head-table, doc-title, status-badge, notes-box, footer-note,
 * etc.) and the 5 non-ZATCA visual template variants (modern/classic/minimal/bold/
 * elegant), extracted from resources/views/pdf/document.blade.php so that every PDF
 * in the app (invoices, estimates, purchase orders, credit/debit notes, zakat,
 * recurring invoices, and payment certificates) can render the same selectable
 * look via the same class names instead of each view re-implementing it.
 *
 * The 'saudi' (ZATCA-standard bilingual) template is deliberately NOT part of this
 * shared class — see document.blade.php's own <style> block, where its CSS (and the
 * entire bilingual saudi-* markup layout) stays local because it's structurally a
 * whole separate tax-invoice/quotation layout, not a reusable variant of the
 * chrome below. Callers that don't support a 'saudi' option (e.g. payment
 * certificates) simply never emit body class="tpl-saudi", so none of this matters
 * to them either way.
 */
class PdfTemplateStyles
{
    /**
     * The base "chrome" rules shared by every template: layout primitives
     * (head-table cell alignment) and typographic building blocks (doc title/
     * number, company/party name, meta lines, section titles) used regardless
     * of which of the 5 visual templates is active.
     */
    public static function baseChrome(): string
    {
        // The trailing "\n" replaces the newline PHP's own closing tag swallows
        // immediately after the short-echo call sites in the views that embed this —
        // without it, the following CSS line would be concatenated onto the same
        // line as output.
        return <<<CSS
        .head-table td { vertical-align: top; padding: 0; }
        .doc-title { font-size: 22px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
        .doc-number { font-size: 13px; color: #555; margin-top: 4px; }
        .company-name { font-size: 17px; font-weight: 700; }
        .meta-line { font-size: 11px; color: #555; margin-top: 2px; }
        .section-title { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 4px; }
        .party-name { font-size: 13px; font-weight: 700; }
        CSS . "\n";
    }

    /** The status badge and page-level notes/footer chrome, also shared across every template. */
    public static function statusAndFooterChrome(): string
    {
        return <<<CSS
        .notes-box { clear: both; margin-top: 60px; padding-top: 10px; font-size: 10.5px; color: #666; }
        .status-badge { display: inline-block; padding: 3px 12px; border-radius: 3px; font-size: 10.5px; font-weight: 700; text-transform: uppercase; }
        .footer-note { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 9.5px; color: #999; }
        CSS . "\n";
    }

    /**
     * The 5 selectable visual templates (modern/classic/minimal/bold/elegant): each
     * one's head-band-or-not, border style, color scheme and font choices, applied via
     * `.tpl-<name>` on <body>. Also carries each template's `.items-table`/`.totals-table`
     * color/border treatment so any view using those two shared class names (invoices,
     * estimates, purchase orders, ... and payment certificates' own richer table) picks
     * up the matching look automatically.
     */
    public static function variants(bool $rtl): string
    {
        // Cairo (Latin + Arabic) is the default for both languages; the old per-language
        // choice (DejaVu Serif/Sans for English, Noto Naskh Arabic for Arabic) stays as a
        // documented fallback rather than being dropped outright.
        $arabicSerif = $rtl ? "'Cairo', 'Noto Naskh Arabic'" : "'Cairo', 'DejaVu Serif'";
        $arabicSans = $rtl ? "'Cairo', 'Noto Naskh Arabic'" : "'Cairo', 'DejaVu Sans'";

        return <<<CSS
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
        .tpl-classic body, .tpl-classic { font-family: {$arabicSerif}, serif; }
        .tpl-classic .head-table { border-bottom: 3px double #16211f; padding-bottom: 14px; margin-bottom: 16px; }
        .tpl-classic .doc-title { font-weight: 700; }
        .tpl-classic .items-table thead { border-top: 1.5px solid #16211f; border-bottom: 1.5px solid #16211f; }
        .tpl-classic .items-table th { font-family: {$arabicSans}, sans-serif; }
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
        .tpl-elegant body, .tpl-elegant { font-family: {$arabicSerif}, serif; color: #2a2a28; }
        .tpl-elegant .head-table { margin-bottom: 8px; }
        .tpl-elegant .doc-title { font-weight: 400; letter-spacing: .2em; font-size: 15px; color: #8a7550; }
        .tpl-elegant .company-name { font-weight: 700; font-size: 19px; letter-spacing: .03em; }
        .tpl-elegant .doc-number { color: #8a7550; }
        .tpl-elegant .section-title { letter-spacing: .12em; }
        .tpl-elegant .items-table { margin-top: 26px; }
        .tpl-elegant .items-table thead th { border-top: 0.75px solid #8a7550; border-bottom: 0.75px solid #8a7550; font-weight: 400; letter-spacing: .08em; color: #8a7550; }
        .tpl-elegant .items-table td { border-bottom: 0.5px solid #e7e1d3; font-family: {$arabicSans}, sans-serif; }
        .tpl-elegant .totals-table .grand { border-top: 0.75px solid #8a7550; color: #8a7550; }
        .tpl-elegant .status-badge { border: 0.75px solid #8a7550; background: #fff; color: #8a7550; letter-spacing: .08em; }
        CSS . "\n";
    }

    /** Everything above in one call, in the same order it originally appeared in document.blade.php — the one a fresh <style> block (e.g. payment-certificate.blade.php's) wants. */
    public static function css(bool $rtl): string
    {
        return self::baseChrome() . "\n" . self::statusAndFooterChrome() . "\n\n" . self::variants($rtl);
    }
}
