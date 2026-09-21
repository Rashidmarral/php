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
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.project_photos.file_required'));
        }
        $mime = $photo->getMimeType();
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.project_photos.type_invalid'));
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.project_photos.max_size'));
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
            'latitude' => $this->coordinate($request->input('latitude'), 90),
            'longitude' => $this->coordinate($request->input('longitude'), 180),
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.project_photos.added'));
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
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.project_photos.removed'));
    }

    /**
     * The device GPS field is populated by browser JS (navigator.geolocation) and is absent
     * whenever geolocation is denied, unsupported, or unavailable — that must never block the
     * upload, so an invalid/out-of-range value is silently dropped rather than rejected.
     */
    private function coordinate(mixed $value, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $value = (float) $value;
        return abs($value) <= $max ? $value : null;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
