<?php

namespace App\Support\Pdf;

use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf
{
    private static function ensureArabicFontRegistered(Dompdf $dompdf): void
    {
        $fontMetrics = $dompdf->getFontMetrics();
        $families = $fontMetrics->getFontFamilies();

        $regular = storage_path('fonts/NotoNaskhArabic-Regular.ttf');
        $bold = storage_path('fonts/NotoNaskhArabic-Bold.ttf');

        // registerFont() is idempotent (it short-circuits once a matching local
        // file is already recorded), so it's safe to call on every request;
        // only the weight actually missing from the cache needs registering.
        if (is_file($regular) && !isset($families['noto naskh arabic']['normal'])) {
            $fontMetrics->registerFont(['family' => 'Noto Naskh Arabic', 'weight' => 'normal', 'style' => 'normal'], 'file://' . $regular);
        }
        if (is_file($bold) && !isset($families['noto naskh arabic']['bold'])) {
            $fontMetrics->registerFont(['family' => 'Noto Naskh Arabic', 'weight' => 'bold', 'style' => 'normal'], 'file://' . $bold);
        }
    }

    /** Renders HTML to raw PDF bytes. Header/attachment handling is the caller's responsibility. */
    public static function output(string $html, string $lang = 'en'): string
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
        $options->set('defaultFont', $lang === 'ar' ? 'Noto Naskh Arabic' : 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        self::ensureArabicFontRegistered($dompdf);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        return $dompdf->output();
    }
}
