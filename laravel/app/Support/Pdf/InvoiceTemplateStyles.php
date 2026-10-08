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
        .itpl-bank-card { margin-top: 16px; }
        .itpl-signature { margin-top: 30px; }
        .itpl-signature .line { border-bottom: 1px solid #9aa5b1; width: 220px; height: 34px; {$signMargin} }
        .itpl-footer-note { margin-top: 22px; text-align: center; font-size: 9px; color: #9aa5b1; }
        CSS . "\n";
    }

    /**
     * The 'bilingual' layout: an upgrade of the existing 'saudi' bordered
     * ZATCA template — new `.itpl-bl-*` classes (kept separate from the
     * shared PdfTemplateStyles::saudiChrome()'s `.saudi-*` classes so the
     * OLD 6-preset system's saudi template is never touched by this stage)
     * with per-row bilingual info labels and a wider items table that
     * splits taxable amount and VAT amount into their own columns.
     */
    public static function bilingualChrome(?InvoiceTemplate $template, bool $rtl): string
    {
        $compact = self::density($template);
        $accent = $template->accent_color ?: '#16233f';
        $headerColor = $template->table_header_color ?: $accent;
        $cellPad = $compact ? '5px 6px' : '7px 9px';
        $fontSize = $compact ? '9px' : '10px';
        $descAlign = $rtl ? 'right' : 'left';
        $totalsFloat = $rtl ? 'float: right;' : 'float: left;';

        return <<<CSS
        .itpl-bl-header { border: 1.5px solid {$accent}; padding: 10px 14px; }
        .itpl-bl-header td { vertical-align: middle; }
        .itpl-bl-company-en { font-size: 13px; font-weight: 700; }
        .itpl-bl-company-ar { font-size: 13px; font-weight: 700; direction: rtl; text-align: right; }
        .itpl-bl-meta { font-size: 9px; color: #444; margin-top: 2px; }
        .itpl-bl-meta.ar { direction: rtl; text-align: right; }
        .itpl-bl-title-bar { text-align: center; background: {$accent}; color: #fff; border: 1.5px solid {$accent}; border-top: none; padding: 7px; font-weight: 700; font-size: 13px; }
        .itpl-bl-info-table { border: 1px solid {$accent}; border-top: none; }
        .itpl-bl-info-table td { border: 1px solid {$accent}; padding: {$cellPad}; font-size: {$fontSize}; vertical-align: top; }
        .itpl-bl-info-table .label-en { font-weight: 700; width: 20%; }
        .itpl-bl-info-table .value { width: 30%; }
        .itpl-bl-info-table .label-ar { font-weight: 700; direction: rtl; text-align: right; width: 20%; }
        .itpl-bl-items-table { margin-top: 0; border: 1px solid {$accent}; }
        .itpl-bl-items-table th { border: 1px solid {$accent}; padding: {$cellPad}; font-size: {$fontSize}; background: {$headerColor}; color: #fff; text-align: center; }
        .itpl-bl-items-table td { border: 1px solid {$accent}; padding: {$cellPad}; font-size: {$fontSize}; text-align: center; }
        .itpl-bl-items-table .desc { text-align: {$descAlign}; }
        .itpl-bl-totals-table { margin-top: 10px; width: 320px; {$totalsFloat} }
        .itpl-bl-totals-table td { border: 1px solid {$accent}; padding: 6px 10px; font-size: 10.5px; }
        .itpl-bl-totals-table .label { background: #f2f1ec; font-weight: 700; }
        .itpl-bl-totals-table .grand td { font-weight: 700; font-size: 12.5px; background: #f7f0dc; }
        .itpl-bl-words { clear: both; margin-top: 8px; border: 1px solid {$accent}; background: {$accent}; color: #fff; padding: 7px 10px; font-size: 10px; text-align: center; }
        .itpl-bl-zatca-pill { display: inline-block; padding: 2px 9px; border-radius: 9px; background: #e5f6ec; color: #157347; font-size: 8.5px; font-weight: 700; margin-bottom: 4px; }
        .itpl-bl-stamp { text-align: center; }
        .itpl-bl-sign-table { margin-top: 22px; }
        .itpl-bl-sign-table td { font-size: 9.5px; padding-top: 26px; border-top: 0.75px solid #999; }
        .itpl-bl-footer { margin-top: 16px; text-align: center; font-size: 9px; color: #555; border-top: 1px solid {$accent}; padding-top: 6px; }
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
