<?php

namespace App\Support\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf
{
    /**
     * Registers Cairo (Regular + Bold), the app-wide brand font, with dompdf. Cairo has
     * full Latin + Arabic glyph coverage, so unlike the old DejaVu Sans/Noto Naskh Arabic
     * split it's registered and used as the default for BOTH languages.
     */
    private static function ensureCairoFontRegistered(Dompdf $dompdf): void
    {
        $fontMetrics = $dompdf->getFontMetrics();
        $families = $fontMetrics->getFontFamilies();

        $regular = storage_path('fonts/Cairo-Regular.ttf');
        $bold = storage_path('fonts/Cairo-Bold.ttf');

        // registerFont() is idempotent (it short-circuits once a matching local
        // file is already recorded), so it's safe to call on every request;
        // only the weight actually missing from the cache needs registering.
        if (is_file($regular) && !isset($families['cairo']['normal'])) {
            $fontMetrics->registerFont(['family' => 'Cairo', 'weight' => 'normal', 'style' => 'normal'], 'file://' . $regular);
        }
        if (is_file($bold) && !isset($families['cairo']['bold'])) {
            $fontMetrics->registerFont(['family' => 'Cairo', 'weight' => 'bold', 'style' => 'normal'], 'file://' . $bold);
        }
    }

    /**
     * Registers Noto Naskh Arabic. No longer the default (Cairo is), but kept registered
     * and listed as a documented CSS fallback in the PDF templates: if Cairo's bundled
     * static instance is ever missing an edge-case Arabic glyph, dompdf degrades to a
     * font still designed for Arabic script rather than falling all the way back to
     * DejaVu Sans (which has much thinner Arabic coverage).
     */
    private static function ensureArabicFontRegistered(Dompdf $dompdf): void
    {
        $fontMetrics = $dompdf->getFontMetrics();
        $families = $fontMetrics->getFontFamilies();

        $regular = storage_path('fonts/NotoNaskhArabic-Regular.ttf');
        $bold = storage_path('fonts/NotoNaskhArabic-Bold.ttf');

        if (is_file($regular) && !isset($families['noto naskh arabic']['normal'])) {
            $fontMetrics->registerFont(['family' => 'Noto Naskh Arabic', 'weight' => 'normal', 'style' => 'normal'], 'file://' . $regular);
        }
        if (is_file($bold) && !isset($families['noto naskh arabic']['bold'])) {
            $fontMetrics->registerFont(['family' => 'Noto Naskh Arabic', 'weight' => 'bold', 'style' => 'normal'], 'file://' . $bold);
        }
    }

    /**
     * Renders HTML to raw PDF bytes. Header/attachment handling is the caller's responsibility.
     *
     * $pageSize accepts InvoiceTemplate::page_size's own values ('a4'/'letter', case-insensitive)
     * or dompdf's own names directly — anything else (including the old callers that never pass
     * this at all) falls back to 'A4', exactly matching every pre-Stage-2 caller's behavior.
     */
    public static function output(string $html, string $lang = 'en', string $pageSize = 'A4'): string
    {
        $cacheDir = storage_path('fonts/cache');
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('fontDir', $cacheDir);
        $options->set('fontCache', $cacheDir);
        $options->set('chroot', [base_path()]);
        // Cairo covers both Latin and Arabic, so it's the default for both languages now
        // (previously this branched to DejaVu Sans for English / Noto Naskh Arabic for Arabic).
        $options->set('defaultFont', 'Cairo');

        $dompdf = new Dompdf($options);
        self::ensureCairoFontRegistered($dompdf);
        self::ensureArabicFontRegistered($dompdf);
        $dompdf->setPaper(strcasecmp($pageSize, 'letter') === 0 ? 'letter' : 'A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
