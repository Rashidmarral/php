<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Project;
use App\Models\ProjectPhoto;

class ProjectPhotoController extends Controller
{
    private const ALLOWED_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function store(string $projectId): void
    {
        $this->verifyCsrf();
        $project = $this->findOwnedProject((int) $projectId);

        if (empty($_FILES['photo']['tmp_name']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Please choose a photo to upload.');
            self::redirect('/app/projects/' . $project['id']);
        }
        $mime = mime_content_type($_FILES['photo']['tmp_name']);
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            $this->flash('error', 'Photo must be a JPG, PNG, or WEBP image.');
            self::redirect('/app/projects/' . $project['id']);
        }
        if ($_FILES['photo']['size'] > 8 * 1024 * 1024) {
            $this->flash('error', 'Photo must be smaller than 8MB.');
            self::redirect('/app/projects/' . $project['id']);
        }

        $dir = BASE_PATH . '/public/uploads/project-photos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = 'project-' . $project['id'] . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        move_uploaded_file($_FILES['photo']['tmp_name'], "{$dir}/{$filename}");

        ProjectPhoto::create([
            'company_id' => Auth::companyId(),
            'project_id' => $project['id'],
            'uploaded_by' => Auth::user()['id'],
            'caption' => trim((string) $this->input('caption', '')),
            'file_path' => "/uploads/project-photos/{$filename}",
            'taken_on' => $this->input('taken_on') ?: date('Y-m-d'),
        ]);

        $this->flash('success', 'Photo added to site diary.');
        self::redirect('/app/projects/' . $project['id']);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $photo = ProjectPhoto::find((int) $id);
        if (!$photo || (int) $photo['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Photo not found.');
        }
        ProjectPhoto::delete($photo['id']);
        $this->flash('success', 'Photo removed.');
        self::redirect('/app/projects/' . $photo['project_id']);
    }

    private function findOwnedProject(int $id): array
    {
        $project = Project::find($id);
        if (!$project || (int) $project['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Project not found.');
        }
        return $project;
    }
}
