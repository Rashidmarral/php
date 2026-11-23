<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ComplianceDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ComplianceController extends Controller
{
    private const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('compliance')) {
            return $redirect;
        }
        $rows = ComplianceDocument::where('company_id', Auth::user()->company_id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get()
            ->toArray();

        return view('app.business-setup.compliance', [
            'rows' => $rows,
            'types' => ComplianceDocument::TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('compliance')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/business-setup/compliance', 'error', t('user.compliance.name_required'));
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'doc_type' => array_key_exists($request->input('doc_type'), ComplianceDocument::TYPES) ? $request->input('doc_type') : 'other',
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'document_number' => trim((string) $request->input('document_number', '')),
            'expiry_date' => $request->input('expiry_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/business-setup/compliance', 'error', $uploadError);
        }

        ComplianceDocument::create($data);
        $this->flash('success', t('user.compliance.added'));
        return redirect('/app/business-setup/compliance');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('compliance')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $doc = $this->findOwned($id);

        $data = [
            'doc_type' => array_key_exists($request->input('doc_type'), ComplianceDocument::TYPES) ? $request->input('doc_type') : 'other',
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'document_number' => trim((string) $request->input('document_number', '')),
            'expiry_date' => $request->input('expiry_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')),
        ];

        // Renewing the expiry date clears the "expiring soon" reminder flag so a fresh
        // reminder can fire again ahead of the new date.
        $currentExpiry = $doc->expiry_date?->format('Y-m-d');
        if (($data['expiry_date'] ?? null) !== $currentExpiry) {
            $data['reminder_sent_at'] = null;
        }

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/business-setup/compliance', 'error', $uploadError);
        }

        $doc->update($data);
        $this->flash('success', t('user.compliance.updated'));
        return redirect('/app/business-setup/compliance');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('compliance')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('manage_business_setup')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', t('user.compliance.removed'));
        return redirect('/app/business-setup/compliance');
    }

    /** @param array $data by reference — sets file_path on success */
    private function handleUpload(Request $request, array &$data): ?string
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return 'File must be a PDF, JPG, or PNG.';
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return 'File must be smaller than 10MB.';
        }
        $filename = 'compliance-' . Auth::user()->company_id . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $file->move(public_path('uploads/compliance'), $filename);
        $data['file_path'] = "/uploads/compliance/{$filename}";
        return null;
    }

    private function findOwned(int $id): ComplianceDocument
    {
        $doc = ComplianceDocument::find($id);
        abort_if(!$doc || $doc->company_id !== Auth::user()->company_id, 404, 'Document not found.');
        return $doc;
    }
}
