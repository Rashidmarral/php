<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectPhotoController extends Controller
{
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('project_photos')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $photo = $request->file('photo');
        if (!$photo || !$photo->isValid()) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'Please choose a photo to upload.');
        }
        $mime = $photo->getMimeType();
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'Photo must be a JPG, PNG, or WEBP image.');
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'Photo must be smaller than 8MB.');
        }

        $filename = 'project-' . $project->id . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $photo->move(public_path('uploads/project-photos'), $filename);

        ProjectPhoto::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'uploaded_by' => Auth::id(),
            'caption' => trim((string) $request->input('caption', '')),
            'file_path' => "/uploads/project-photos/{$filename}",
            'taken_on' => $request->input('taken_on') ?: now()->format('Y-m-d'),
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', 'Photo added to site diary.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('project_photos')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $photo = ProjectPhoto::find($id);
        abort_if(!$photo || $photo->company_id !== Auth::user()->company_id, 404, 'Photo not found.');
        $projectId = $photo->project_id;
        $photo->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', 'Photo removed.');
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
