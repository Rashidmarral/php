<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ToolboxTalk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Toolbox talk record: a logged safety briefing held on site — date, topic, who
 * conducted it, how many attended. A standalone append-only record, same shape as
 * SiteLog: no status, no edit form, just log/delete.
 */
class ToolboxTalkController extends Controller
{
    // Same upload convention as PunchListController/SafetyIncidentController.
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function index(int $projectId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $talks = ToolboxTalk::where('project_id', $project->id)
            ->orderByDesc('talk_date')
            ->orderByDesc('created_at')
            ->get();

        return view('app.toolbox-talks.index', [
            'project' => $project->toArray(),
            'talks' => $talks->toArray(),
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

        $topic = trim((string) $request->input('topic'));
        if ($topic === '') {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.toolbox_talks.topic_required'));
        }

        $data = [
            'company_id' => $project->company_id,
            'project_id' => $project->id,
            'talk_date' => $request->input('talk_date') ?: now()->format('Y-m-d'),
            'topic' => $topic,
            'conducted_by' => Auth::id(),
            'attendee_count' => $request->filled('attendee_count') ? (int) $request->input('attendee_count') : null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', $uploadError);
        }

        ToolboxTalk::create($data);
        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.toolbox_talks.logged'));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('safety_tracking')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $talk = $this->findOwned($id);
        $projectId = $talk->project_id;
        if ($talk->photo_path) {
            $file = public_path($talk->photo_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $talk->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.toolbox_talks.removed'));
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
            return t('user.toolbox_talks.photo_type_invalid');
        }
        if ($photo->getSize() > 8 * 1024 * 1024) {
            return t('user.toolbox_talks.photo_too_large');
        }
        $filename = 'toolbox-' . $data['project_id'] . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $photo->move(public_path('uploads/toolbox-talks'), $filename);
        $data['photo_path'] = "/uploads/toolbox-talks/{$filename}";
        return null;
    }

    private function findOwned(int $id): ToolboxTalk
    {
        $talk = ToolboxTalk::find($id);
        abort_if(!$talk || $talk->company_id !== Auth::user()->company_id, 404, 'Toolbox talk not found.');
        return $talk;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
