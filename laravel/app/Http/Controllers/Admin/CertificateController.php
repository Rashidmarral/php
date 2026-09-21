<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

/** Public trust badges (CR/Chamber membership, ZATCA compliance, ISO, etc.) shown on the marketing site's About page. */
class CertificateController extends Controller
{
    private const ALLOWED_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    public function index(): View
    {
        return view('admin.certificates.index', [
            'certificates' => Certificate::orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $titleEn = trim((string) $request->input('title_en'));
        if ($titleEn === '') {
            return $this->redirectWithFlash('/admin/certificates', 'error', t('admin.certificates.title_required'));
        }

        /** @var UploadedFile|null $file */
        $file = $request->file('image');
        if (!$file || !$file->isValid() || !isset(self::ALLOWED_TYPES[$file->getMimeType()])) {
            return $this->redirectWithFlash('/admin/certificates', 'error', t('admin.certificates.image_type_invalid'));
        }
        if ($file->getSize() > 3 * 1024 * 1024) {
            return $this->redirectWithFlash('/admin/certificates', 'error', t('admin.certificates.image_max_size'));
        }
        $filename = 'certificate-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$file->getMimeType()];
        $file->move(public_path('uploads/certificates'), $filename);

        Certificate::create([
            'title_en' => $titleEn,
            'title_ar' => trim((string) $request->input('title_ar', '')) ?: null,
            'issuer_en' => trim((string) $request->input('issuer_en', '')) ?: null,
            'issuer_ar' => trim((string) $request->input('issuer_ar', '')) ?: null,
            'image_path' => "/uploads/certificates/{$filename}",
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);

        $this->flash('success', t('admin.certificates.added'));
        return redirect('/admin/certificates');
    }

    public function toggle(int $id): RedirectResponse
    {
        $certificate = $this->findOrFail($id);
        $certificate->update(['is_active' => !$certificate->is_active]);
        return redirect('/admin/certificates');
    }

    public function destroy(int $id): RedirectResponse
    {
        $certificate = $this->findOrFail($id);
        $file = public_path($certificate->image_path);
        if (is_file($file)) {
            unlink($file);
        }
        $certificate->delete();
        $this->flash('success', t('admin.certificates.removed'));
        return redirect('/admin/certificates');
    }

    private function findOrFail(int $id): Certificate
    {
        $certificate = Certificate::find($id);
        abort_if(!$certificate, 404, 'Certificate not found.');
        return $certificate;
    }
}
