<?php

namespace App\Http\Controllers;

use App\Support\Feature;
use App\Support\Pdf\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

abstract class Controller
{
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
        $label = Feature::ALL[$key] ?? $key;
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
}
