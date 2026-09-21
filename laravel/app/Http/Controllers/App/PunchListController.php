<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\PunchListItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PunchListController extends Controller
{
    // Reuses ProjectPhotoController's exact upload convention (allowed types + size limit) since
    // a punch-list defect photo is functionally the same kind of upload.
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('punch_list')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $title = trim((string) $request->input('title'));
        if ($title === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.punch_list.title_required'));
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'title' => $title,
            'description' => trim((string) $request->input('description', '')) ?: null,
            'location' => trim((string) $request->input('location', '')) ?: null,
            'status' => 'open',
            'priority' => array_key_exists($request->input('priority'), PunchListItem::PRIORITIES) ? $request->input('priority') : 'medium',
            'assigned_to' => $this->ownedUserId($request->input('assigned_to'), $project->company_id),
            'due_date' => $request->input('due_date') ?: null,
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', $uploadError);
        }

        PunchListItem::create($data);
        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.punch_list.added'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('punch_list')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $item = $this->findOwned($id);

        $data = [];
        if ($request->filled('status') && array_key_exists($request->input('status'), PunchListItem::STATUSES)) {
            $status = $request->input('status');
            $data['status'] = $status;
            $data['resolved_at'] = $status === 'resolved' ? now() : null;
        }
        if ($request->has('assigned_to')) {
            $data['assigned_to'] = $this->ownedUserId($request->input('assigned_to'), $item->company_id);
        }

        if (!empty($data)) {
            $item->update($data);
        }

        return $this->redirectWithFlash('/app/projects/' . $item->project_id, 'success', t('user.punch_list.updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('punch_list')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $item = $this->findOwned($id);
        $projectId = $item->project_id;
        if ($item->photo_path) {
            $file = public_path($item->photo_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $item->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.punch_list.removed'));
    }

    /** @param array $data by reference — sets photo_path on success */
    private function handleUpload(Request $request, array &$data): ?string
    {
        $photo = $request->file('photo');
        if (!$photo || !$photo->isValid()) {
            return null;
        }
        $mime = $photo->getMimeType();
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return 'Photo must be a JPG, PNG, or WEBP image.';
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return 'Photo must be smaller than 8MB.';
        }
        $filename = 'punch-' . $data['project_id'] . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $photo->move(public_path('uploads/punch-list'), $filename);
        $data['photo_path'] = "/uploads/punch-list/{$filename}";
        return null;
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

    private function findOwned(int $id): PunchListItem
    {
        $item = PunchListItem::find($id);
        abort_if(!$item || $item->company_id !== Auth::user()->company_id, 404, 'Punch list item not found.');
        return $item;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
