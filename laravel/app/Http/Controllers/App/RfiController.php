<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Request for Information: a project-scoped threaded Q&A, built on the exact same
 * pattern as SupportTicket/SupportTicketMessage (see SupportTicketController for the
 * CRUD/reply-flow this mirrors) — a question raised against a project, optionally
 * referencing a specific Document/drawing, that an assignee answers over a message
 * thread until the RFI is marked answered/closed.
 */
class RfiController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfi')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $rfis = Rfi::where('project_id', $project->id)
            ->orderByDesc('rfi_number')
            ->get();

        return view('app.rfis.index', [
            'project' => $project->toArray(),
            'rfis' => $rfis,
            'statuses' => Rfi::STATUSES,
        ]);
    }

    public function create(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfi')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        return view('app.rfis.create', [
            'project' => $project->toArray(),
            'teamMembers' => User::where('company_id', $project->company_id)->orderBy('name')->get(),
            'documents' => Document::where('company_id', $project->company_id)
                ->where('project_id', $project->id)
                ->currentVersion()
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('rfi')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $subject = trim((string) $request->input('subject'));
        $question = trim((string) $request->input('question'));
        if ($subject === '' || $question === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/rfis/new', 'error', t('user.rfi.subject_question_required'));
        }

        $nextNumber = (int) Rfi::where('project_id', $project->id)->max('rfi_number') + 1;

        $rfi = Rfi::create([
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'rfi_number' => $nextNumber,
            'subject' => $subject,
            'question' => $question,
            'raised_by' => Auth::id(),
            'assigned_to' => $this->ownedUserId($request->input('assigned_to'), $project->company_id),
            'status' => 'open',
            'due_date' => $request->input('due_date') ?: null,
            'document_id' => $this->ownedDocumentId($request->input('document_id'), $project),
        ]);

        [$attachmentPath, $attachmentName, $error] = $this->storeAttachment($request->file('attachment'), $rfi->id);
        if ($error) {
            return $this->redirectWithFlash('/app/projects/' . $project->id . '/rfis/new', 'error', $error);
        }

        RfiMessage::create([
            'rfi_id' => $rfi->id,
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'message' => $question,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        $this->flash('success', t('user.rfi.submitted', ['number' => $rfi->displayNumber()]));
        return redirect('/app/rfis/' . $rfi->id);
    }

    public function show(int $id): View
    {
        $rfi = $this->findOwned($id);
        $rfi->load(['messages', 'raisedByUser', 'assignee', 'document', 'project']);

        return view('app.rfis.show', [
            'rfi' => $rfi,
            'statuses' => Rfi::STATUSES,
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $rfi = $this->findOwned($id);
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return $this->redirectWithFlash('/app/rfis/' . $id, 'error', t('user.rfi.reply_message_required'));
        }

        [$attachmentPath, $attachmentName, $error] = $this->storeAttachment($request->file('attachment'), $rfi->id);
        if ($error) {
            return $this->redirectWithFlash('/app/rfis/' . $id, 'error', $error);
        }

        RfiMessage::create([
            'rfi_id' => $rfi->id,
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'message' => $message,
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
        ]);

        // A reply from the assignee answers the RFI; a follow-up from anyone else (including
        // the requester asking for clarification) reopens it — mirroring SupportTicketController
        // ::reply()'s own channel-based status nudge.
        $isAssigneeReply = $rfi->assigned_to && (int) $rfi->assigned_to === Auth::id();
        $rfi->update([
            'status' => $isAssigneeReply ? 'answered' : ($rfi->status === 'closed' ? 'closed' : 'open'),
        ]);

        $this->flash('success', t('user.rfi.reply_sent'));
        return redirect('/app/rfis/' . $id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $rfi = $this->findOwned($id);
        $status = $request->input('status');
        if (!array_key_exists($status, Rfi::STATUSES)) {
            return $this->redirectWithFlash('/app/rfis/' . $id, 'error', t('user.rfi.invalid_status'));
        }
        $rfi->update(['status' => $status]);
        $this->flash('success', t('user.rfi.status_updated'));
        return redirect('/app/rfis/' . $id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $rfi = $this->findOwned($id);
        $projectId = $rfi->project_id;
        $rfi->delete(); // RfiMessage rows for this rfi_id are harmless orphans, same as support tickets never cascade-deleting their messages either.
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.rfi.removed'));
    }

    /** @return array{0: ?string, 1: ?string, 2: ?string} [path, name, error] */
    private function storeAttachment(?UploadedFile $file, int $rfiId): array
    {
        if (!$file) {
            return [null, null, null];
        }
        if (!$file->isValid()) {
            return [null, null, t('user.rfi.attachment_upload_failed')];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return [null, null, t('user.rfi.attachment_max_size')];
        }
        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return [null, null, t('user.rfi.attachment_type_not_allowed', ['allowed' => implode(', ', self::ALLOWED_EXT)])];
        }

        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move(public_path("uploads/rfis/{$rfiId}"), $storedName);

        return ["/uploads/rfis/{$rfiId}/{$storedName}", $originalName, null];
    }

    /** Only assigns to a user within the acting company — never let a cross-tenant id slip into assigned_to. */
    private function ownedUserId(mixed $id, int $companyId): ?int
    {
        if (!$id) {
            return null;
        }
        $user = User::find((int) $id);
        return ($user && $user->company_id === $companyId) ? $user->id : null;
    }

    /** Only links a document that belongs to this exact project — never a cross-tenant/cross-project id. */
    private function ownedDocumentId(mixed $id, Project $project): ?int
    {
        if (!$id) {
            return null;
        }
        $document = Document::find((int) $id);
        return ($document && $document->company_id === $project->company_id && (int) $document->project_id === $project->id) ? $document->id : null;
    }

    private function findOwned(int $id): Rfi
    {
        $rfi = Rfi::find($id);
        abort_if(!$rfi || $rfi->company_id !== Auth::user()->company_id, 404, 'RFI not found.');
        return $rfi;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
