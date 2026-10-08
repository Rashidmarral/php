<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Material/shop-drawing submittal approval workflow. A submittal's history is the full
 * sequence of file revisions (SubmittalRevision, each one numbered and immutable — never
 * edited/replaced, only appended, same "immutable history" precedent as
 * SubcontractPayment's cumulative chain / Document's new version-control fields) plus its
 * own status changes, decided by whoever holds the 'approve_documents' ability — the same
 * Gate ExtensionOfTimeController reuses for contractual sign-off weight.
 */
class SubmittalController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'dwg', 'zip'];
    private const MAX_BYTES = 20 * 1024 * 1024;

    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('submittals')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $submittals = Submittal::where('project_id', $project->id)
            ->orderByDesc('submittal_number')
            ->get();

        return view('app.submittals.index', [
            'project' => $project->toArray(),
            'submittals' => $submittals,
            'statuses' => Submittal::STATUSES,
        ]);
    }

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('submittals')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        return view('app.submittals.create', [
            'project' => $project->toArray(),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('submittals')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/submittals/new', 'error', t('user.submittals.title_required'));
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/submittals/new', 'error', t('user.submittals.file_required'));
        }
        [$filePath, $fileName, $error] = $this->storeFile($file, $project->id);
        if ($error) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/submittals/new', 'error', $error);
        }

        $nextNumber = (int) Submittal::where('project_id', $project->id)->max('submittal_number') + 1;

        $submittal = Submittal::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'submittal_number' => $nextNumber,
            'title' => $title,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'spec_section' => trim((string) $request->input('spec_section', '')) ?: null,
            'status' => 'submitted',
            'submitted_by' => Auth::id(),
            'due_date' => $request->input('due_date') ?: null,
        ]);

        SubmittalRevision::create([
            'submittal_id' => $submittal->id,
            'revision_number' => 1,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        $this->flash('success', t('user.submittals.submitted', ['number' => $submittal->displayNumber()]));
        return redirect('/app/submittals/' . $submittal->id);
    }

    public function show(int $id): View
    {
        $submittal = $this->findOwned($id);
        $submittal->load(['revisions', 'submitter', 'reviewer', 'project']);

        return view('app.submittals.show', [
            'submittal' => $submittal,
            'statuses' => Submittal::STATUSES,
        ]);
    }

    public function uploadRevision(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('submittals')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $submittal = $this->findOwned($id);

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return $this->redirectWithFlash('/app/submittals/' . $id, 'error', t('user.submittals.file_required'));
        }
        [$filePath, $fileName, $error] = $this->storeFile($file, $submittal->project_id);
        if ($error) {
            return $this->redirectWithFlash('/app/submittals/' . $id, 'error', $error);
        }

        $nextRevisionNumber = (int) SubmittalRevision::where('submittal_id', $submittal->id)->max('revision_number') + 1;

        SubmittalRevision::create([
            'submittal_id' => $submittal->id,
            'revision_number' => $nextRevisionNumber,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        // A new revision re-opens the review cycle — it supersedes whatever was previously
        // reviewed, so the status returns to 'submitted' regardless of what it was before
        // (approved, rejected, revise_resubmit, ...). The prior status transitions themselves
        // are never erased — they remain visible as part of this submittal's history.
        $submittal->update(['status' => 'submitted']);

        $this->flash('success', t('user.submittals.revision_uploaded', ['number' => $nextRevisionNumber]));
        return redirect('/app/submittals/' . $id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('approve_documents')) {
            return $redirect;
        }
        $submittal = $this->findOwned($id);
        $status = $request->input('status');
        if (!array_key_exists($status, Submittal::STATUSES)) {
            return $this->redirectWithFlash('/app/submittals/' . $id, 'error', t('user.submittals.invalid_status'));
        }

        $submittal->update([
            'status' => $status,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        $this->flash('success', t('user.submittals.status_updated'));
        return redirect('/app/submittals/' . $id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $submittal = $this->findOwned($id);
        $projectId = $submittal->project_id;

        foreach (SubmittalRevision::where('submittal_id', $submittal->id)->get() as $revision) {
            $file = public_path($revision->file_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        SubmittalRevision::where('submittal_id', $submittal->id)->delete();
        $submittal->delete();

        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.submittals.removed'));
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [path, name, error] */
    private function storeFile(\Illuminate\Http\UploadedFile $file, int $projectId): array
    {
        if ($file->getSize() > self::MAX_BYTES) {
            return [null, null, t('user.submittals.max_size')];
        }
        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return [null, null, t('user.submittals.type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_EXT)])];
        }

        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move(public_path("uploads/submittals/{$projectId}"), $storedName);

        return ["/uploads/submittals/{$projectId}/{$storedName}", $originalName, null];
    }

    private function findOwned(int $id): Submittal
    {
        $submittal = Submittal::find($id);
        abort_if(!$submittal || $submittal->company_id !== Auth::user()->company_id, 404, 'Submittal not found.');
        return $submittal;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
