<?php

namespace App\Support\Pdf;

use App\Models\InvoiceTemplate;

/**
 * CSS for Stage 2's per-company, per-document-type InvoiceTemplate rendering
 * system (resources/views/pdf/document-v2.blade.php and
 * resources/views/pdf/payment-certificate-v2.blade.php) — a separate,
 * additive sibling to PdfTemplateStyles, which keeps rendering the original
 * 6-preset system unchanged for any company with no InvoiceTemplate row yet
 * (see Company::activeInvoiceTemplateFor()).
 *
 * Three layout families, matching InvoiceTemplate::layout exactly as
 * documented on the invoice_templates migration:
 *   - card       Rounded-card foundation: box-shadow + border-radius cards,
 *                a 2-column header (company info card + QR/ZATCA-badge
 *                card), a colored table header row, and a totals panel that
 *                is either a solid accent-filled "boxed" rounded panel or a
 *                plain bordered table — see cardSubStyle() for which
 *                preset_key values pick "boxed" totals vs "plain" totals.
 *   - bilingual  An upgrade of the existing 'saudi' bordered ZATCA layout:
 *                per-row bilingual (EN/AR) info-table labels, separate
 *                taxable-amount and VAT-amount item columns, an optional
 *                company stamp slot, and the ZATCA cleared/reported badge.
 *   - letterhead An uploaded letterhead image spans the header; falls back
 *                to a plain company-name header when none is uploaded yet.
 *
 * All three respect $template->density ('compact' tightens padding/font,
 * 'comfortable' is roomier) via the $compact flag threaded through every
 * method here.
 */
class InvoiceTemplateStyles
{
    /**
     * "boxed" totals (solid accent-filled rounded panel) vs "plain" totals
     * (bordered table, no fill) for the 'card' layout — layout itself only
     * means "this is a card-style document" per the migration's comment, so
     * this is the sub-branch that decides the totals treatment from which
     * preset a template started from. A template not started from one of
     * these presets (a fully custom one, or the legacy classic/minimal/
     * bold/elegant carry-forward rows) defaults to the plain style, which
     * is the safer, more universally-flattering choice absent a stronger
     * design opinion from the preset it was cloned from.
     */
    public static function cardUsesBoxedTotals(?InvoiceTemplate $template): bool
    {
        return in_array($template->preset_key ?? null, ['contractor_bold', 'foundation_charcoal'], true);
    }

    private static function density(?InvoiceTemplate $template): bool
    {
        return ($template->density ?? 'compact') !== 'comfortable';
    }

    /** Shared base rules used by all three layout families: resets, typography scale driven by density. */
    public static function baseChrome(?InvoiceTemplate $template, bool $rtl): string
    {
        $compact = self::density($template);
        $bodyFont = $compact ? '11px' : '12.5px';
        $lineHeight = $compact ? '1.35' : '1.5';
        $numAlign = $rtl ? 'left' : 'right';

        return <<<CSS
        .itpl-doc { font-size: {$bodyFont}; line-height: {$lineHeight}; }
        .itpl-doc table { width: 100%; border-collapse: collapse; }
        .itpl-doc .num { text-align: {$numAlign}; }
        .itpl-doc .clearfix { clear: both; }
        .itpl-doc .muted { color: #6b7280; }
        .itpl-doc .small { font-size: 9.5px; }
        .itpl-doc .ar-text { direction: rtl; text-align: right; }
        CSS . "\n";
    }

    /**
     * The 'card' layout: rounded corners + subtle shadows on the header
     * info cards, the items-table wrapper and the totals panel — the
     * "structural foundation" the migration's comment describes as covering
     * the same visual space our old modern/classic/minimal/bold/elegant
     * templates occupied, now built as real dompdf-rendered CSS instead of
     * 5 separate hand-tuned templates.
     */
    public static function cardChrome(?InvoiceTemplate $template, bool $rtl): string
    {
        $compact = self::density($template);
        $accent = $template->accent_color ?: '#16233f';
        $headerColor = $template->table_header_color ?: $accent;
        $totalsColor = $template->totalsColor();
        $cellPad = $compact ? '6px 8px' : '9px 12px';
        $align = $rtl ? 'right' : 'left';
        $alignEnd = $rtl ? 'left' : 'right';
        $totalsFloat = $rtl ? 'float: left;' : 'float: right;';
        $signMargin = $rtl ? 'margin-right: 0; margin-left: auto;' : 'margin-left: 0; margin-right: auto;';

        return <<<CSS
        .itpl-card-topbar { height: 6px; border-radius: 5px; background: {$accent}; margin-bottom: 16px; }
        .itpl-card-header td { vertical-align: top; }
        .itpl-card-company-name { font-size: 17px; font-weight: 700; }
        .itpl-card-vat-badge { display: inline-block; margin-top: 6px; padding: 3px 11px; border-radius: 11px; background: #f1f3f5; color: #52606d; font-size: 9.5px; }
        .itpl-card-doc-badge { display: inline-block; padding: 6px 18px; border-radius: 15px; color: #fff; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; background: {$accent}; }
        .itpl-card-box { border: 1px solid #eceff1; border-radius: 10px; padding: 12px 14px; background: #fafbfc; box-shadow: 0 1px 4px rgba(15,23,42,0.08); }
        .itpl-card-box h4 { margin: 0 0 4px; font-size: 9.5px; text-transform: uppercase; letter-spacing: .04em; color: #8a95a3; font-weight: 700; }
        .itpl-zatca-pill { display: inline-block; padding: 3px 10px; border-radius: 10px; background: #e5f6ec; color: #157347; font-size: 9px; font-weight: 700; margin-bottom: 6px; }
        .itpl-table-card { border: 1px solid #eceff1; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 4px rgba(15,23,42,0.08); margin-top: 16px; }
        .itpl-items-table th { background: {$headerColor}; color: #fff; padding: {$cellPad}; font-size: 9.5px; text-transform: uppercase; letter-spacing: .03em; text-align: {$align}; }
        .itpl-items-table th.num { text-align: {$alignEnd}; }
        .itpl-items-table td { padding: {$cellPad}; border-bottom: 1px solid #f1f2f4; }
        .itpl-items-table tr:last-child td { border-bottom: none; }
        .itpl-items-table tr:nth-child(even) td { background: #fbfbfc; }
        .itpl-totals-wrap { margin-top: 16px; }
        .itpl-totals-box { width: 270px; {$totalsFloat} border-radius: 10px; }
        .itpl-totals-box.plain { border: 1px solid #eceff1; box-shadow: 0 1px 4px rgba(15,23,42,0.08); padding: 12px 14px; }
        .itpl-totals-box.boxed { background: {$totalsColor}; color: #fff; padding: 14px 16px; box-shadow: 0 2px 6px rgba(15,23,42,0.15); }
        .itpl-totals-box td { padding: 4px 0; font-size: 10.5px; }
        .itpl-totals-box.boxed td { color: rgba(255,255,255,0.88); }
        .itpl-totals-box .grand td { font-size: 13.5px; font-weight: 700; padding-top: 8px; }
        .itpl-totals-box.plain .grand td { border-top: 1.5px solid {$accent}; color: {$accent}; }
        .itpl-totals-box.boxed .grand td { border-top: 1px solid rgba(255,255,255,0.35); color: #fff; }
        .itpl-vat-summary-wrap { margin-top: 14px; clear: both; }
        .itpl-vat-summary-wrap h4 { margin: 0 0 4px; }
        .itpl-vat-summary-table { border: 1px solid #eceff1; border-radius: 8px; overflow: hidden; }
        .itpl-vat-summary-table th { background: {$headerColor}; color: #fff; padding: {$cellPad}; font-size: 9.5px; text-align: {$alignEnd}; }
        .itpl-vat-summary-table td { padding: {$cellPad}; font-size: 10px; border-bottom: 1px solid #f1f2f4; }
        .itpl-vat-summary-table tr:last-child td { border-bottom: none; }
        .itpl-bank-card { margin-top: 16px; }
        .itpl-signature { margin-top: 30px; }
        .itpl-signature .line { border-bottom: 1px solid #9aa5b1; width: 220px; height: 34px; {$signMargin} }
        .itpl-footer-note { margin-top: 22px; text-align: center; font-size: 9px; color: #9aa5b1; }
        CSS . "\n";
    }

    /**
     * The 'bilingual' layout: ported from Daftari's real `bilingual_classic`
     * mPDF template (daftari/resources/views/documents/print/pdf.blade.php)
     * rather than the earlier from-scratch approximation this replaced —
     * see itpl-bilingual.blade.php's own docblock for the structural
     * decisions carried over (thin top accent rule instead of a boxed
     * header, an unboxed EN|logo|AR header, a centered title under a
     * border-bottom rule instead of a solid filled bar, row-separator
     * table borders instead of a full grid box, and a two-column bank-
     * details/totals footer block). `.itpl-bl-*` stays its own class
     * namespace, separate from PdfTemplateStyles::saudiChrome()'s
     * `.saudi-*` classes, so the OLD 6-preset system's saudi template is
     * never touched by this stage. Also shared verbatim by
     * payment-certificate-v2.blade.php's own `layout === 'bilingual'`
     * branch (same class names, same CSS) — see that file's comment.
     */
    public static function bilingualChrome(?InvoiceTemplate $template, bool $rtl): string
    {
        $compact = self::density($template);
        $accent = $template->accent_color ?: '#16233f';
        $headerColor = $template->table_header_color ?: null;
        $headerBg = $headerColor ? "background: {$headerColor};" : '';
        $headerColorText = $headerColor ? '#fff' : '#0f172a';
        $cellPad = $compact ? '4px 6px' : '6px 9px';
        $fontSize = $compact ? '9px' : '10px';
        $descAlign = $rtl ? 'right' : 'left';
        $totalsFloat = $rtl ? 'float: right;' : 'float: left;';

        return <<<CSS
        .itpl-bl-topbar { border: 1.5px solid {$accent}; margin-bottom: 10px; }
        .itpl-bl-topbar td { height: 6px; padding: 0; }
        .itpl-bl-header td { vertical-align: top; }
        .itpl-bl-company-en { font-size: 13px; font-weight: 700; color: #0f172a; }
        .itpl-bl-company-ar { font-size: 13px; font-weight: 700; direction: rtl; text-align: right; color: #0f172a; }
        .itpl-bl-meta { font-size: 9px; color: #444; margin-top: 2px; }
        .itpl-bl-meta.ar { direction: rtl; text-align: right; }
        .itpl-bl-title-bar { text-align: center; border-bottom: 1.5px solid {$accent}; margin-top: 10px; padding-bottom: 6px; font-weight: 700; font-size: 16px; color: #0f172a; }
        .itpl-bl-status-pill { display: inline-block; margin-top: 6px; padding: 2px 12px; border-radius: 10px; font-size: 9px; font-weight: 700; }
        .itpl-bl-info-table { margin-top: 10px; border: 0.75px solid #cbd5e1; }
        .itpl-bl-info-table td { border-bottom: 0.5px solid #cbd5e1; padding: {$cellPad}; font-size: {$fontSize}; vertical-align: top; }
        .itpl-bl-info-table tr:last-child td { border-bottom: none; }
        .itpl-bl-info-table .label-en { font-weight: 700; color: #475569; width: 20%; }
        .itpl-bl-info-table .value { width: 60%; text-align: center; }
        .itpl-bl-info-table .label-ar { font-weight: 700; color: #475569; direction: rtl; text-align: right; width: 20%; }
        .itpl-bl-items-table { margin-top: 10px; border-bottom: 1.5px solid #16211f; }
        .itpl-bl-items-table th { {$headerBg} border-bottom: 1.5px solid #16211f; border-right: 0.5px solid #e2e8f0; padding: {$cellPad}; font-size: {$fontSize}; color: {$headerColorText}; text-align: center; line-height: 1.3; }
        .itpl-bl-items-table th:last-child { border-right: none; }
        .itpl-bl-items-table th .ar-text { display: block; font-weight: 400; font-size: 8px; }
        .itpl-bl-items-table td { border-bottom: 0.5px solid #e2e8f0; border-right: 0.5px solid #e2e8f0; padding: {$cellPad}; font-size: {$fontSize}; text-align: center; }
        .itpl-bl-items-table td:last-child { border-right: none; }
        .itpl-bl-items-table tr:last-child td { border-bottom: none; }
        .itpl-bl-items-table .desc { text-align: {$descAlign}; }
        .itpl-bl-vat-summary-title { margin-top: 10px; font-size: {$fontSize}; font-weight: 700; color: #0f172a; }
        .itpl-bl-vat-summary-table { margin-top: 4px; border: 0.75px solid #cbd5e1; }
        .itpl-bl-vat-summary-table th { background: #f1f5f9; border-bottom: 1px solid #cbd5e1; border-right: 0.5px solid #e2e8f0; padding: {$cellPad}; font-size: {$fontSize}; text-align: center; }
        .itpl-bl-vat-summary-table th:last-child, .itpl-bl-vat-summary-table td:last-child { border-right: none; }
        .itpl-bl-vat-summary-table td { border-right: 0.5px solid #e2e8f0; padding: {$cellPad}; font-size: {$fontSize}; text-align: center; }
        .itpl-bl-footer-wrap { margin-top: 10px; }
        .itpl-bl-words { font-size: {$fontSize}; color: #475569; }
        .itpl-bl-words strong { color: #0f172a; }
        .itpl-bl-bank { margin-top: 8px; font-size: {$fontSize}; color: #475569; line-height: 1.4; }
        .itpl-bl-totals-table { width: 300px; {$totalsFloat} }
        .itpl-bl-totals-table td { padding: 3px 0; font-size: 10px; color: #475569; }
        .itpl-bl-totals-table .label { font-weight: 400; }
        .itpl-bl-totals-table .grand td { font-weight: 700; font-size: 12.5px; padding-top: 6px; border-top: 1.5px solid #16211f; color: #16211f; }
        .itpl-bl-zatca-pill { display: inline-block; padding: 2px 9px; border-radius: 9px; background: #e5f6ec; color: #157347; font-size: 8.5px; font-weight: 700; margin-bottom: 4px; }
        .itpl-bl-stamp { text-align: center; }
        .itpl-bl-sign-table { margin-top: 24px; }
        .itpl-bl-sign-table td { font-size: 9.5px; padding-top: 24px; border-top: 0.75px solid #94a3b8; }
        .itpl-bl-footer { margin-top: 14px; text-align: center; font-size: 9px; color: #94a3b8; border-top: 0.75px solid #cbd5e1; padding-top: 6px; }
        CSS . "\n";
    }

    /**
     * The 'letterhead' layout: a plain, compact bordered document meant to
     * print onto a company's own paper — either a real uploaded banner
     * image (letterhead_path) or, absent one, a simple company-name/logo
     * header fallback (see document-v2.blade.php's `@if ($template->
     * letterhead_path)` branch for where that fallback is applied).
     */
    public static function letterheadChrome(?InvoiceTemplate $template, bool $rtl): string
    {
        $compact = self::density($template);
        $accent = $template->accent_color ?: '#16233f';
        $headerColor = $template->table_header_color ?: '#f4f5f6';
        $cellPad = $compact ? '5px 7px' : '7px 9px';
        $align = $rtl ? 'right' : 'left';
        $alignEnd = $rtl ? 'left' : 'right';
        $totalsFloat = $rtl ? 'float: left;' : 'float: right;';
        $signMargin = $rtl ? 'margin-right: 0; margin-left: auto;' : 'margin-left: 0; margin-right: auto;';

        return <<<CSS
        .itpl-lh-banner { width: 100%; display: block; margin-bottom: 12px; }
        .itpl-lh-fallback-header td { vertical-align: middle; }
        .itpl-lh-fallback-name { font-size: 16px; font-weight: 700; }
        .itpl-lh-title { text-align: center; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin: 10px 0 14px; color: {$accent}; }
        .itpl-lh-info-table td { padding: 3px 0; font-size: 10.5px; }
        .itpl-lh-info-table .label { font-weight: 700; color: #52606d; width: 26%; }
        .itpl-lh-items-table { margin-top: 14px; border: 1px solid #d8dce0; }
        .itpl-lh-items-table th { border: 1px solid #d8dce0; background: {$headerColor}; padding: {$cellPad}; font-size: 9.5px; text-align: {$align}; }
        .itpl-lh-items-table th.num { text-align: {$alignEnd}; }
        .itpl-lh-items-table td { border: 1px solid #d8dce0; padding: {$cellPad}; font-size: 10px; }
        .itpl-lh-vat-summary-table { margin-top: 10px; border: 1px solid #d8dce0; }
        .itpl-lh-vat-summary-table th { border: 1px solid #d8dce0; background: {$headerColor}; padding: {$cellPad}; font-size: 9.5px; }
        .itpl-lh-vat-summary-table td { border: 1px solid #d8dce0; padding: {$cellPad}; font-size: 10px; }
        .itpl-lh-words { margin-top: 10px; font-size: 10px; }
        .itpl-lh-totals-table { width: 260px; {$totalsFloat} margin-top: 10px; }
        .itpl-lh-totals-table td { padding: 4px 8px; font-size: 10.5px; border-bottom: 1px solid #eceff1; }
        .itpl-lh-totals-table .grand td { font-weight: 700; font-size: 12px; border-top: 1.5px solid {$accent}; border-bottom: none; color: {$accent}; }
        .itpl-lh-bank { clear: both; margin-top: 20px; border-top: 1px solid #d8dce0; padding-top: 10px; font-size: 10px; }
        .itpl-lh-bank h4 { margin: 0 0 4px; font-size: 9.5px; text-transform: uppercase; color: #8a95a3; }
        .itpl-lh-signature { margin-top: 26px; }
        .itpl-lh-signature .line { border-bottom: 1px solid #9aa5b1; width: 200px; height: 30px; {$signMargin} }
        .itpl-lh-footer { margin-top: 18px; text-align: center; font-size: 9px; color: #9aa5b1; }
        CSS . "\n";
    }

    /** Everything for one InvoiceTemplate's layout, in one call — what document-v2.blade.php's <style> block wants. */
    public static function css(?InvoiceTemplate $template, bool $rtl): string
    {
        return self::baseChrome($template, $rtl) . "\n"
            . self::cardChrome($template, $rtl) . "\n"
            . self::bilingualChrome($template, $rtl) . "\n"
            . self::letterheadChrome($template, $rtl);
    }
}
