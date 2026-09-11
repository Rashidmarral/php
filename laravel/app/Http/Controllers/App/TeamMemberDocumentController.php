<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\TeamMemberDocument;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TeamMemberDocumentController extends Controller
{
    private const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function index(int $userId): View|RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwnedMember($userId);

        $rows = TeamMemberDocument::where('user_id', $member->id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get()
            ->toArray();

        return view('app.team.documents', [
            'member' => $member,
            'rows' => $rows,
            'types' => TeamMemberDocument::TYPES,
        ]);
    }

    public function store(Request $request, int $userId): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwnedMember($userId);

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash("/app/team/{$member->id}/documents", 'error', 'Document name is required.');
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'user_id' => $member->id,
            'doc_type' => array_key_exists($request->input('doc_type'), TeamMemberDocument::TYPES) ? $request->input('doc_type') : 'other',
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'document_number' => trim((string) $request->input('document_number', '')),
            'expiry_date' => $request->input('expiry_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash("/app/team/{$member->id}/documents", 'error', $uploadError);
        }

        TeamMemberDocument::create($data);
        $this->flash('success', 'Document added.');
        return redirect("/app/team/{$member->id}/documents");
    }

    public function update(Request $request, int $userId, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwnedMember($userId);
        $doc = $this->findOwnedDocument($id, $member->id);

        $data = [
            'doc_type' => array_key_exists($request->input('doc_type'), TeamMemberDocument::TYPES) ? $request->input('doc_type') : 'other',
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
            return $this->redirectWithFlash("/app/team/{$member->id}/documents", 'error', $uploadError);
        }

        $doc->update($data);
        $this->flash('success', 'Document updated.');
        return redirect("/app/team/{$member->id}/documents");
    }

    public function destroy(int $userId, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('manage_team')) {
            return $redirect;
        }
        $member = $this->findOwnedMember($userId);
        $this->findOwnedDocument($id, $member->id)->delete();
        $this->flash('success', 'Document removed.');
        return redirect("/app/team/{$member->id}/documents");
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
        $filename = 'team-doc-' . Auth::user()->company_id . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $file->move(public_path('uploads/team-documents'), $filename);
        $data['file_path'] = "/uploads/team-documents/{$filename}";
        return null;
    }

    /** Only returns the team member if they belong to the acting company — never let one company read/write another's team member records. */
    private function findOwnedMember(int $userId): User
    {
        $member = User::find($userId);
        abort_if(!$member || $member->company_id !== Auth::user()->company_id, 404, 'Team member not found.');
        return $member;
    }

    private function findOwnedDocument(int $id, int $userId): TeamMemberDocument
    {
        $doc = TeamMemberDocument::find($id);
        abort_if(!$doc || $doc->user_id !== $userId, 404, 'Document not found.');
        return $doc;
    }
}
