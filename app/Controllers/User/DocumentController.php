<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\Document;
use App\Models\Project;

class DocumentController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'zip'];

    public function __construct()
    {
        Feature::requireOrRedirect('documents');
    }

    public function index(): void
    {
        $companyId = Auth::companyId();
        $documents = Document::query(
            'SELECT d.*, p.name AS project_name, p.name_ar AS project_name_ar, u.name AS uploaded_by_name FROM documents d
             LEFT JOIN projects p ON p.id = d.project_id
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.company_id = ? ORDER BY d.created_at DESC',
            [$companyId]
        )->fetchAll();
        $projects = Project::where('company_id', $companyId, 'name ASC');

        $this->view('user/documents/index', ['pageTitle' => 'Documents', 'documents' => $documents, 'projects' => $projects], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $companyId = Auth::companyId();

        if (empty($_FILES['file']['tmp_name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please choose a file to upload.');
            self::redirect('/app/documents');
        }
        if ($_FILES['file']['size'] > 15 * 1024 * 1024) {
            $this->flash('error', 'File must be smaller than 15MB.');
            self::redirect('/app/documents');
        }

        $originalName = $_FILES['file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            $this->flash('error', 'File type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXT));
            self::redirect('/app/documents');
        }

        $dir = BASE_PATH . "/public/uploads/documents/{$companyId}";
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], "{$dir}/{$storedName}");

        $projectId = $this->input('project_id') ?: null;
        if ($projectId) {
            $project = Project::find((int) $projectId);
            if (!$project || (int) $project['company_id'] !== $companyId) {
                $projectId = null;
            }
        }

        Document::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'uploaded_by' => Auth::user()['id'],
            'name' => $originalName,
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'file_path' => "/uploads/documents/{$companyId}/{$storedName}",
            'file_type' => $ext,
            'file_size' => $_FILES['file']['size'],
        ]);

        $this->flash('success', 'Document uploaded.');
        self::redirect('/app/documents');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');
        $document = Document::find((int) $id);
        if (!$document || (int) $document['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Document not found.');
        }
        $file = BASE_PATH . '/public' . $document['file_path'];
        if (is_file($file)) {
            unlink($file);
        }
        Document::delete($document['id']);
        $this->flash('success', 'Document removed.');
        self::redirect('/app/documents');
    }
}
