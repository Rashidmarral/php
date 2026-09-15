<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DocumentController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'zip'];

    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $documents = DB::table('documents as d')
            ->leftJoin('projects as p', 'p.id', '=', 'd.project_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')
            ->where('d.company_id', $companyId)
            ->orderByDesc('d.created_at')
            ->select('d.*', 'p.name as project_name', 'p.name_ar as project_name_ar', 'u.name as uploaded_by_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.documents.index', [
            'documents' => $documents,
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->redirectWithFlash('/app/documents', 'error', 'Please choose a file to upload.');
        }
        if ($file->getSize() > 15 * 1024 * 1024) {
            return $this->redirectWithFlash('/app/documents', 'error', 'File must be smaller than 15MB.');
        }

        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $this->redirectWithFlash('/app/documents', 'error', 'File type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXT));
        }

        $fileSize = $file->getSize();
        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move(public_path("uploads/documents/{$companyId}"), $storedName);

        $projectId = $request->input('project_id') ?: null;
        if ($projectId) {
            $project = Project::find((int) $projectId);
            if (!$project || $project->company_id !== $companyId) {
                $projectId = null;
            }
        }

        Document::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'uploaded_by' => Auth::id(),
            'name' => $originalName,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'file_path' => "/uploads/documents/{$companyId}/{$storedName}",
            'file_type' => $ext,
            'file_size' => $fileSize,
        ]);

        $this->flash('success', 'Document uploaded.');
        return redirect('/app/documents');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $document = Document::find($id);
        abort_if(!$document || $document->company_id !== Auth::user()->company_id, 404, 'Document not found.');

        $file = public_path($document->file_path);
        if (is_file($file)) {
            unlink($file);
        }
        $document->delete();
        $this->flash('success', 'Document removed.');
        return redirect('/app/documents');
    }
}
