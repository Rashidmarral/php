<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Converts an uploaded multi-page PDF plan set (site plan, floor plans, elevations, details)
 * into one JPEG per page, using pdftoppm (poppler-utils) via Symfony\Process — never
 * shell_exec()/exec() directly.
 *
 * pdftoppm ships on this dev box and on most Linux hosts, but is NOT guaranteed on shared
 * cPanel hosting. Callers MUST check isAvailable() first and surface a translated error
 * (TakeoffController::store()) instead of attempting the conversion, rather than crashing or
 * silently producing nothing.
 *
 * Pages are rendered at 150 DPI — plenty for on-screen measurement on a canvas, never
 * printed — so a 20-page set stays a reasonable size on disk instead of bloating storage.
 */
class TakeoffPdfConverter
{
    /** Matches the real BuildXact product's own cap on a single PDF upload. */
    public const MAX_PAGES = 20;

    public const RESOLUTION_DPI = 150;

    public static function isAvailable(): bool
    {
        return (new ExecutableFinder())->find('pdftoppm') !== null;
    }

    /** The PDF's page count via pdfinfo, or null if pdfinfo isn't available or the file can't be read. */
    public static function pageCount(string $pdfPath): ?int
    {
        $binary = (new ExecutableFinder())->find('pdfinfo');
        if (!$binary) {
            return null;
        }

        $process = new Process([$binary, $pdfPath]);
        $process->run();
        if (!$process->isSuccessful()) {
            return null;
        }

        foreach (explode("\n", $process->getOutput()) as $line) {
            if (preg_match('/^Pages:\s+(\d+)/', $line, $m)) {
                return (int) $m[1];
            }
        }

        return null;
    }

    /**
     * Converts every page of $pdfPath into $destDir/page-1.jpg, page-2.jpg, etc. (created if
     * missing). Rejects a set over $maxPages pages without ever rendering past the cap.
     *
     * @return array{success:bool,files?:array<int,string>,error?:string,page_count?:int}
     *         error is one of 'unavailable' (pdftoppm missing — see isAvailable()),
     *         'too_many_pages' (page_count is set), or 'conversion_failed'.
     */
    public static function convert(string $pdfPath, string $destDir, int $maxPages = self::MAX_PAGES): array
    {
        $binary = (new ExecutableFinder())->find('pdftoppm');
        if (!$binary) {
            return ['success' => false, 'error' => 'unavailable'];
        }

        $knownCount = self::pageCount($pdfPath);
        if ($knownCount !== null && $knownCount > $maxPages) {
            return ['success' => false, 'error' => 'too_many_pages', 'page_count' => $knownCount];
        }

        if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
            return ['success' => false, 'error' => 'conversion_failed'];
        }

        $prefix = $destDir . '/raw';
        $process = new Process([
            $binary, '-jpeg', '-r', (string) self::RESOLUTION_DPI, '-jpegopt', 'quality=85',
            // Render at most one page past the cap — enough to detect an oversized set
            // without wasting time rasterizing the rest of a huge mistaken upload.
            '-l', (string) ($maxPages + 1),
            $pdfPath, $prefix,
        ]);
        $process->setTimeout(180);
        $process->run();

        if (!$process->isSuccessful()) {
            return ['success' => false, 'error' => 'conversion_failed'];
        }

        $numbered = self::collectNumberedPages($prefix);

        if (count($numbered) === 0) {
            return ['success' => false, 'error' => 'conversion_failed'];
        }

        if (count($numbered) > $maxPages) {
            foreach ($numbered as $file) {
                @unlink($file);
            }
            return ['success' => false, 'error' => 'too_many_pages', 'page_count' => count($numbered)];
        }

        $files = [];
        $pageNumber = 1;
        foreach ($numbered as $file) {
            $targetName = 'page-' . $pageNumber . '.jpg';
            rename($file, $destDir . '/' . $targetName);
            $files[] = $targetName;
            $pageNumber++;
        }

        return ['success' => true, 'files' => $files];
    }

    /** @return array<int,string> Full paths of pdftoppm's output files, in page order. */
    private static function collectNumberedPages(string $prefix): array
    {
        $numbered = [];
        foreach (glob($prefix . '-*.jpg') ?: [] as $file) {
            if (preg_match('/-(\d+)\.jpg$/', $file, $m)) {
                $numbered[(int) $m[1]] = $file;
            }
        }

        // A single-page PDF with some poppler builds collapses to the bare prefix with no
        // digit suffix at all ("raw.jpg") instead of "raw-1.jpg" — normalize that to page 1.
        if ($numbered === [] && is_file($prefix . '.jpg')) {
            $numbered[1] = $prefix . '.jpg';
        }

        ksort($numbered);
        return array_values($numbered);
    }
}
