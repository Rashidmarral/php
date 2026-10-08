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

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        // Default view: only the current (latest) version of every document. ?show_history=1
        // additionally lists every superseded version, each one still individually downloadable.
        $showHistory = $request->query('show_history') === '1';

        $query = DB::table('documents as d')
            ->leftJoin('projects as p', 'p.id', '=', 'd.project_id')
            ->leftJoin('users as u', 'u.id', '=', 'd.uploaded_by')
            ->where('d.company_id', $companyId);
        if (!$showHistory) {
            $query->where('d.is_current', true);
        }
        $documents = $query
            ->orderByDesc('d.created_at')
            ->select('d.*', 'p.name as project_name', 'p.name_ar as project_name_ar', 'u.name as uploaded_by_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Version chips ("v1, v2 (current)") for every listed document that has more than
        // one version — built from the real Document rows so the chain is never guessed.
        $versionChains = [];
        foreach ($documents as $row) {
            $doc = Document::find($row['id']);
            if (!$doc) {
                continue;
            }
            $chain = $doc->versionChain();
            if (count($chain) > 1) {
                $versionChains[$row['id']] = array_map(fn (Document $d) => $d->toArray(), $chain);
            }
        }

        return view('app.documents.index', [
            'documents' => $documents,
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'showHistory' => $showHistory,
            'versionChains' => $versionChains,
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
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.file_required'));
        }
        if ($file->getSize() > 15 * 1024 * 1024) {
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.max_size'));
        }

        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_EXT)]));
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

        $this->flash('success', t('user.documents.uploaded'));
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
        $this->flash('success', t('user.documents.removed'));
        return redirect('/app/documents');
    }

    /**
     * Uploads a new version of an existing document: creates a NEW documents row
     * (version = old.version + 1, supersedes_id = old.id, is_current = true) and flips
     * the OLD row's is_current to false. The old row is never edited/deleted otherwise —
     * full history is preserved, same "immutable history, append new state" precedent as
     * SubcontractPayment's cumulative chain.
     */
    public function uploadVersion(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $old = Document::find($id);
        abort_if(!$old || $old->company_id !== $companyId, 404, 'Document not found.');

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.file_required'));
        }
        if ($file->getSize() > 15 * 1024 * 1024) {
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.max_size'));
        }

        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return $this->redirectWithFlash('/app/documents', 'error', t('user.documents.type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_EXT)]));
        }

        $fileSize = $file->getSize();
        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move(public_path("uploads/documents/{$companyId}"), $storedName);

        DB::transaction(function () use ($old, $originalName, $ext, $fileSize, $storedName, $companyId, $request) {
            Document::create([
                'company_id' => $companyId,
                'project_id' => $old->project_id,
                'uploaded_by' => Auth::id(),
                'name' => $originalName,
                'name_ar' => trim((string) $request->input('name_ar', '')) ?: $old->name_ar,
                'file_path' => "/uploads/documents/{$companyId}/{$storedName}",
                'file_type' => $ext,
                'file_size' => $fileSize,
                'version' => (int) $old->version + 1,
                'supersedes_id' => $old->id,
                'is_current' => true,
            ]);

            $old->update(['is_current' => false]);
        });

        $this->flash('success', t('user.documents.version_uploaded'));
        return redirect('/app/documents');
    }
}
