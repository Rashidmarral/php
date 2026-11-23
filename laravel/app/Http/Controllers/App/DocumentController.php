<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentPin;
use App\Models\Project;
use App\Models\PunchListItem;
use App\Models\Rfi;
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

    /**
     * Short URL-friendly slugs for DocumentPin::pinnable_type, used in query params
     * and the pin-creation form instead of exposing the full namespaced class name.
     *
     * @var array<string, class-string>
     */
    private const PINNABLE_TYPES = [
        'punch_list_item' => PunchListItem::class,
        'rfi' => Rfi::class,
    ];

    /**
     * The pinned-markup viewer: draws the document's image on a canvas (same
     * canvasPoint() pixel-coordinate mechanic as takeoffs/show.blade.php) with every
     * existing pin overlaid at its stored (x,y). When ?pin_type=&pin_id= names a
     * PunchListItem/Rfi owned by this company, the view opens in "placing a pin"
     * mode: the next canvas click is POSTed to storePin() below.
     */
    public function viewer(Request $request, int $id): View
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $document = Document::find($id);
        abort_if(!$document || $document->company_id !== $companyId, 404, 'Document not found.');

        $pins = DocumentPin::where('document_id', $document->id)
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get()
            ->map(function (DocumentPin $pin) use ($companyId) {
                $target = $this->findPinnable($pin->pinnable_type, $pin->pinnable_id, $companyId);
                return [
                    'id' => $pin->id,
                    'x' => (float) $pin->x,
                    'y' => (float) $pin->y,
                    'label' => $pin->label,
                    'link' => $target ? $this->pinnableLink($pin->pinnable_type, $target) : null,
                ];
            })
            ->values()
            ->all();

        $placing = null;
        $pinType = $request->query('pin_type');
        $pinId = (int) $request->query('pin_id');
        if ($pinId && array_key_exists((string) $pinType, self::PINNABLE_TYPES)) {
            $target = $this->findPinnable(self::PINNABLE_TYPES[$pinType], $pinId, $companyId);
            if ($target) {
                $placing = [
                    'type' => $pinType,
                    'id' => $target->id,
                    'label' => $pinType === 'punch_list_item' ? $target->title : ($target->displayNumber() . ' — ' . $target->subject),
                ];
            }
        }

        return view('app.documents.viewer', [
            'document' => $document,
            'isImage' => $document->isPinnableImage(),
            'pins' => $pins,
            'placing' => $placing,
        ]);
    }

    /** Places a new pin from a canvas click in documents.viewer's "placing a pin" mode. */
    public function storePin(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('documents')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $document = Document::find($id);
        abort_if(!$document || $document->company_id !== $companyId, 404, 'Document not found.');

        $pinType = (string) $request->input('pinnable_type');
        if (!array_key_exists($pinType, self::PINNABLE_TYPES)) {
            return $this->redirectWithFlash('/app/documents/' . $document->id . '/viewer', 'error', t('user.documents.pin_invalid_target'));
        }
        $modelClass = self::PINNABLE_TYPES[$pinType];
        $target = $this->findPinnable($modelClass, (int) $request->input('pinnable_id'), $companyId);
        abort_if(!$target, 404, 'Linked record not found.');

        $x = (float) $request->input('x');
        $y = (float) $request->input('y');
        if ($x < 0 || $x > 100 || $y < 0 || $y > 100) {
            return $this->redirectWithFlash('/app/documents/' . $document->id . '/viewer', 'error', t('user.documents.pin_invalid_position'));
        }

        $label = trim((string) $request->input('label', ''));
        if ($label === '') {
            $label = $pinType === 'punch_list_item' ? $target->title : $target->displayNumber();
        }

        DocumentPin::create([
            'document_id' => $document->id,
            'company_id' => $companyId,
            'x' => $x,
            'y' => $y,
            'pinnable_type' => $modelClass,
            'pinnable_id' => $target->id,
            'created_by' => Auth::id(),
            'label' => $label,
        ]);

        $this->flash('success', t('user.documents.pin_created'));
        return redirect('/app/documents/' . $document->id . '/viewer');
    }

    /** Finds a PunchListItem/Rfi by class+id, scoped to $companyId — never returns a cross-tenant row. */
    private function findPinnable(string $modelClass, int $id, int $companyId): PunchListItem|Rfi|null
    {
        if (!$id || !in_array($modelClass, self::PINNABLE_TYPES, true)) {
            return null;
        }
        /** @var PunchListItem|Rfi|null $target */
        $target = $modelClass::find($id);
        return ($target && $target->company_id === $companyId) ? $target : null;
    }

    private function pinnableLink(string $modelClass, PunchListItem|Rfi $target): string
    {
        return $modelClass === Rfi::class
            ? '/app/rfis/' . $target->id
            : '/app/projects/' . $target->project_id . '#punch-list';
    }
}
