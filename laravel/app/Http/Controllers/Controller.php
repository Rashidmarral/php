<?php

namespace App\Http\Controllers;

use App\Support\Feature;
use App\Support\Pdf\Pdf;
use App\Support\SpreadsheetBoqImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class Controller
{
    /** Allowed rows-per-page choices for the shared admin.partials.pagination selector. */
    protected const PER_PAGE_OPTIONS = [10, 20, 50];

    /** Reads and validates ?per_page= against PER_PAGE_OPTIONS, falling back to $default for anything else. */
    protected function perPage(Request $request, int $default = 20): int
    {
        $value = (int) $request->query('per_page', $default);
        return in_array($value, self::PER_PAGE_OPTIONS, true) ? $value : $default;
    }

    protected function flash(string $type, string $message): void
    {
        session()->push("flash.{$type}", $message);
    }

    /** Sets a flash message and redirects in one call, mirroring the original app's flash()+redirect() pairing. */
    protected function redirectWithFlash(string $path, string $type, string $message): RedirectResponse
    {
        $this->flash($type, $message);
        return redirect($path);
    }

    /**
     * Mirrors the original App\Core\Auth::requireAbility(): if the current user lacks the
     * Gate ability, flashes an error and returns a redirect to /app — the caller must
     * `return` it. Returns null when the ability check passes (nothing to redirect).
     */
    protected function requireAbility(string $ability): ?RedirectResponse
    {
        if (auth()->user()->can($ability)) {
            return null;
        }
        $label = auth()->user()->roleLabel();
        return $this->redirectWithFlash('/app', 'error', "Your role ({$label}) does not have permission to do that.");
    }

    /** Mirrors the original App\Core\Feature::requireOrRedirect(): redirects to Billing if the plan lacks $key. */
    protected function requireFeature(string $key): ?RedirectResponse
    {
        if (Feature::allows($key)) {
            return null;
        }
        $label = array_key_exists($key, Feature::ALL) ? t('feature.' . $key) : $key;
        return $this->redirectWithFlash('/app/billing', 'error', "{$label} isn't included in your current plan. Upgrade to unlock it.");
    }

    /** Renders resources/views/pdf/document.blade.php with $data and returns it as a downloadable PDF. */
    protected function streamPdf(array $data, string $filename): Response
    {
        $html = view('pdf.document', $data)->render();
        $pdf = Pdf::output($html, $data['lang'] ?? 'en');

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Downloadable CSV template for SpreadsheetBoqImporter::parse() — the exact
     * headers/order it recognizes for $target, plus one example row, so a user
     * always has a working starting point instead of guessing column names.
     * Shared by EstimateController and BoqController's own importTemplate()
     * actions (same fputcsv() streaming convention as TeamController's WPS
     * export / Admin\CompanyController's/Admin\PaymentController's CSV exports).
     */
    protected function streamCsvTemplate(string $target, string $filename): Response
    {
        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, SpreadsheetBoqImporter::templateHeaders($target));
        fputcsv($csv, SpreadsheetBoqImporter::templateExampleRow($target));
        rewind($csv);
        $body = stream_get_contents($csv);
        fclose($csv);

        return response($body, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Flashes a SpreadsheetBoqImporter::parse() result: a translated success
     * summary (imported/failed counts) whenever at least one row was actually
     * imported, one translated error line per row failure so nothing fails
     * silently, and a translated "nothing to import" error only when the whole
     * file produced zero valid rows AND zero row errors (e.g. every row in the
     * file was entirely blank).
     */
    protected function flashImportResult(array $result, string $summaryKey, string $rowErrorKey, string $noRowsKey): void
    {
        $importedCount = count($result['valid']);
        $errorCount = count($result['errors']);

        foreach ($result['errors'] as $err) {
            $this->flash('error', t($rowErrorKey, ['row' => $err['row'], 'reason' => $err['reason']]));
        }
        if ($importedCount > 0) {
            $this->flash('success', t($summaryKey, ['imported' => $importedCount, 'failed' => $errorCount]));
        } elseif ($errorCount === 0) {
            $this->flash('error', t($noRowsKey));
        }
    }
}
