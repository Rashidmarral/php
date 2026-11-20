<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\SafetyIncident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * HSE safety incident log: a standalone, non-threaded, project-scoped record (near
 * miss, first aid, lost time injury, property damage, ...) carried through a simple
 * open -> under_investigation -> closed status lifecycle. Mirrors PunchListController's
 * exact shape — no edit form, no message thread, just log/update-status/delete.
 */
class SafetyIncidentController extends Controller
{
    // Same upload convention as PunchListController (and, before it, ProjectPhotoController):
    // a safety-incident photo is functionally the same kind of upload.
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $incidents = SafetyIncident::where('project_id', $project->id)
            ->orderByDesc('incident_number')
            ->get();

        return view('app.safety-incidents.index', [
            'project' => $project->toArray(),
            'incidents' => $incidents->toArray(),
            'statuses' => SafetyIncident::STATUSES,
            'severities' => SafetyIncident::SEVERITIES,
            'suggestedTypes' => SafetyIncident::SUGGESTED_TYPES,
        ]);
    }

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.safety_incidents.description_required'));
        }

        $incidentType = $this->resolveOtherField($request, 'incident_type');
        if ($incidentType === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.safety_incidents.type_required'));
        }

        $nextNumber = (int) SafetyIncident::where('project_id', $project->id)->max('incident_number') + 1;

        $data = [
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'incident_number' => $nextNumber,
            'incident_date' => $request->input('incident_date') ?: now()->format('Y-m-d'),
            'incident_type' => $incidentType,
            'severity' => array_key_exists($request->input('severity'), SafetyIncident::SEVERITIES) ? $request->input('severity') : 'low',
            'description' => $description,
            'location' => trim((string) $request->input('location', '')) ?: null,
            'injured_person_name' => trim((string) $request->input('injured_person_name', '')) ?: null,
            'reported_by' => Auth::id(),
            'corrective_action' => trim((string) $request->input('corrective_action', '')) ?: null,
            'status' => 'open',
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', $uploadError);
        }

        $incident = SafetyIncident::create($data);
        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.safety_incidents.logged', ['number' => $incident->displayNumber()]));
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $incident = $this->findOwned($id);

        $status = $request->input('status');
        if (!array_key_exists($status, SafetyIncident::STATUSES)) {
            return $this->redirectWithFlash('/app/projects/' . $incident->project_id, 'error', t('user.safety_incidents.invalid_status'));
        }

        $data = ['status' => $status];
        if ($request->filled('corrective_action')) {
            $data['corrective_action'] = trim((string) $request->input('corrective_action'));
        }
        $incident->update($data);

        return $this->redirectWithFlash('/app/projects/' . $incident->project_id, 'success', t('user.safety_incidents.status_updated'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $incident = $this->findOwned($id);
        $projectId = $incident->project_id;
        if ($incident->photo_path) {
            $file = public_path($incident->photo_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $incident->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.safety_incidents.removed'));
    }

    /**
     * Mirrors Supplier's trade_category "select with common options + Other free-text
     * escape hatch": the form posts $field as either one of the suggested options or the
     * literal "other", and when it's "other" the real value comes from {$field}_other.
     */
    private function resolveOtherField(Request $request, string $field): string
    {
        $value = trim((string) $request->input($field, ''));
        if ($value === 'other') {
            return trim((string) $request->input($field . '_other', ''));
        }
        return $value;
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
            return t('user.safety_incidents.photo_type_invalid');
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return t('user.safety_incidents.photo_too_large');
        }
        $filename = 'incident-' . $data['project_id'] . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $photo->move(public_path('uploads/safety-incidents'), $filename);
        $data['photo_path'] = "/uploads/safety-incidents/{$filename}";
        return null;
    }

    private function findOwned(int $id): SafetyIncident
    {
        $incident = SafetyIncident::find($id);
        abort_if(!$incident || $incident->company_id !== Auth::user()->company_id, 404, 'Safety incident not found.');
        return $incident;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
