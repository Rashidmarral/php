<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\ComplianceDocument;

class ComplianceController extends Controller
{
    private const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function __construct()
    {
        Feature::requireOrRedirect('compliance');
    }

    public function index(): void
    {
        $rows = ComplianceDocument::query(
            'SELECT * FROM compliance_documents WHERE company_id = ? ORDER BY (expiry_date IS NULL), expiry_date ASC',
            [Auth::companyId()]
        )->fetchAll();

        $this->view('user/business-setup/compliance', [
            'pageTitle' => 'Compliance Documents',
            'rows' => $rows,
            'types' => ComplianceDocument::TYPES,
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');

        $name = trim((string) $this->input('name'));
        if ($name === '') {
            $this->flash('error', 'Document name is required.');
            self::redirect('/app/business-setup/compliance');
        }

        $data = [
            'company_id' => Auth::companyId(),
            'doc_type' => array_key_exists($this->input('doc_type'), ComplianceDocument::TYPES) ? $this->input('doc_type') : 'other',
            'name' => $name,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'document_number' => trim((string) $this->input('document_number', '')),
            'expiry_date' => $this->input('expiry_date') ?: null,
            'notes' => trim((string) $this->input('notes', '')),
        ];

        $uploadError = $this->handleUpload($data);
        if ($uploadError) {
            $this->flash('error', $uploadError);
            self::redirect('/app/business-setup/compliance');
        }

        ComplianceDocument::create($data);
        $this->flash('success', 'Compliance document added.');
        self::redirect('/app/business-setup/compliance');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $doc = $this->findOwned((int) $id);

        $data = [
            'doc_type' => array_key_exists($this->input('doc_type'), ComplianceDocument::TYPES) ? $this->input('doc_type') : 'other',
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'document_number' => trim((string) $this->input('document_number', '')),
            'expiry_date' => $this->input('expiry_date') ?: null,
            'notes' => trim((string) $this->input('notes', '')),
        ];

        // Renewing the expiry date clears the "expiring soon" reminder flag so a fresh
        // reminder can fire again ahead of the new date.
        if (($data['expiry_date'] ?? null) !== $doc['expiry_date']) {
            $data['reminder_sent_at'] = null;
        }

        $uploadError = $this->handleUpload($data);
        if ($uploadError) {
            $this->flash('error', $uploadError);
            self::redirect('/app/business-setup/compliance');
        }

        ComplianceDocument::update($doc['id'], $data);
        $this->flash('success', 'Compliance document updated.');
        self::redirect('/app/business-setup/compliance');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('manage_business_setup');
        $doc = $this->findOwned((int) $id);
        ComplianceDocument::delete($doc['id']);
        $this->flash('success', 'Compliance document removed.');
        self::redirect('/app/business-setup/compliance');
    }

    /** @param array $data by reference — sets file_path on success */
    private function handleUpload(array &$data): ?string
    {
        if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        $mime = mime_content_type($_FILES['file']['tmp_name']);
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return 'File must be a PDF, JPG, or PNG.';
        }
        if ($_FILES['file']['size'] > 10 * 1024 * 1024) {
            return 'File must be smaller than 10MB.';
        }
        $dir = BASE_PATH . '/public/uploads/compliance';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'compliance-' . Auth::companyId() . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        move_uploaded_file($_FILES['file']['tmp_name'], "{$dir}/{$filename}");
        $data['file_path'] = "/uploads/compliance/{$filename}";
        return null;
    }

    private function findOwned(int $id): array
    {
        $doc = ComplianceDocument::find($id);
        if (!$doc || (int) $doc['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Document not found.');
        }
        return $doc;
    }
}
