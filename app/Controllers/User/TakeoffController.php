<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Project;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;

class TakeoffController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $takeoffs = Takeoff::query(
            'SELECT t.*, p.name AS project_name FROM takeoffs t LEFT JOIN projects p ON p.id = t.project_id WHERE t.company_id = ? ORDER BY t.created_at DESC',
            [$companyId]
        )->fetchAll();
        $this->view('user/takeoffs/index', ['pageTitle' => 'Digital Takeoff', 'takeoffs' => $takeoffs], 'layouts/app');
    }

    public function create(): void
    {
        $projects = Project::where('company_id', Auth::companyId(), 'name ASC');
        $this->view('user/takeoffs/create', ['pageTitle' => 'New Takeoff', 'projects' => $projects], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $companyId = Auth::companyId();
        $name = trim((string) $this->input('name'));

        if ($name === '') {
            $this->flash('error', 'Takeoff name is required.');
            self::redirect('/app/takeoffs/create');
        }

        $imagePath = null;
        if (!empty($_FILES['plan_image']['tmp_name']) && $_FILES['plan_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['plan_image']['tmp_name']);
            if (!isset($allowed[$mime])) {
                $this->flash('error', 'Plan image must be a JPG, PNG, or WEBP file.');
                self::redirect('/app/takeoffs/create');
            }
            if ($_FILES['plan_image']['size'] > 8 * 1024 * 1024) {
                $this->flash('error', 'Plan image must be smaller than 8MB.');
                self::redirect('/app/takeoffs/create');
            }
            $dir = BASE_PATH . "/public/uploads/takeoffs/{$companyId}";
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['plan_image']['tmp_name'], "{$dir}/{$filename}");
            $imagePath = "/uploads/takeoffs/{$companyId}/{$filename}";
        }

        $projectId = $this->input('project_id') ?: null;
        if ($projectId) {
            $project = Project::find((int) $projectId);
            if (!$project || (int) $project['company_id'] !== $companyId) {
                $projectId = null;
            }
        }

        $id = Takeoff::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'name' => $name,
            'plan_image_path' => $imagePath,
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
        ]);

        self::redirect('/app/takeoffs/' . $id);
    }

    public function show(string $id): void
    {
        $takeoff = $this->findOwned((int) $id);
        $measurements = TakeoffMeasurement::where('takeoff_id', $takeoff['id'], 'id ASC');
        $totalCost = array_sum(array_map(fn($m) => (float) $m['total_cost'], $measurements));

        $this->view('user/takeoffs/show', [
            'pageTitle' => $takeoff['name'],
            'takeoff' => $takeoff,
            'measurements' => $measurements,
            'totalCost' => $totalCost,
        ], 'layouts/app');
    }

    public function calibrate(string $id): void
    {
        $this->verifyCsrf();
        $takeoff = $this->findOwned((int) $id);

        $pixelDistance = (float) $this->input('pixel_distance', 0);
        $realLength = (float) $this->input('real_length', 0);

        if ($pixelDistance <= 0 || $realLength <= 0) {
            $this->jsonError('Invalid calibration values.');
            return;
        }

        Takeoff::update($takeoff['id'], ['scale_px_per_unit' => $pixelDistance / $realLength]);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'scale' => $pixelDistance / $realLength]);
    }

    public function addMeasurement(string $id): void
    {
        $this->verifyCsrf();
        $takeoff = $this->findOwned((int) $id);

        $type = (string) $this->input('type');
        $label = trim((string) $this->input('label')) ?: ucfirst($type);
        $value = (float) $this->input('value', 0);
        $unitCost = (float) $this->input('unit_cost', 0);
        $points = (string) $this->input('points', '[]');

        if (!in_array($type, ['length', 'area', 'count'], true) || $value <= 0) {
            $this->jsonError('Invalid measurement.');
            return;
        }

        $unit = $type === 'length' ? $takeoff['scale_unit'] : ($type === 'area' ? $takeoff['scale_unit'] . '²' : 'ea');

        $measurementId = TakeoffMeasurement::create([
            'takeoff_id' => $takeoff['id'],
            'type' => $type,
            'label' => $label,
            'points_json' => $points,
            'value' => $value,
            'unit' => $unit,
            'unit_cost' => $unitCost,
            'total_cost' => $value * $unitCost,
        ]);

        header('Content-Type: application/json');
        echo json_encode(['ok' => true, 'id' => $measurementId]);
    }

    public function deleteMeasurement(string $id, string $measurementId): void
    {
        $this->verifyCsrf();
        $takeoff = $this->findOwned((int) $id);
        $measurement = TakeoffMeasurement::find((int) $measurementId);
        if ($measurement && (int) $measurement['takeoff_id'] === $takeoff['id']) {
            TakeoffMeasurement::delete($measurement['id']);
        }
        self::redirect('/app/takeoffs/' . $takeoff['id']);
    }

    public function convertToEstimate(string $id): void
    {
        $this->verifyCsrf();
        $takeoff = $this->findOwned((int) $id);
        $measurements = TakeoffMeasurement::where('takeoff_id', $takeoff['id'], 'id ASC');

        if (empty($measurements)) {
            $this->flash('error', 'Add at least one measurement before converting to an estimate.');
            self::redirect('/app/takeoffs/' . $takeoff['id']);
        }

        $total = array_sum(array_map(fn($m) => (float) $m['total_cost'], $measurements));

        $estimateId = Estimate::create([
            'company_id' => $takeoff['company_id'],
            'project_id' => $takeoff['project_id'],
            'client_id' => null,
            'title' => $takeoff['name'] . ' — Takeoff Estimate',
            'status' => 'draft',
            'total' => $total,
        ]);

        foreach ($measurements as $m) {
            EstimateItem::create([
                'estimate_id' => $estimateId,
                'description' => $m['label'] . ' (' . number_format((float) $m['value'], 2) . ' ' . $m['unit'] . ')',
                'qty' => $m['value'],
                'unit_cost' => $m['unit_cost'],
                'total' => $m['total_cost'],
            ]);
        }

        $this->flash('success', 'Takeoff converted to a new estimate.');
        self::redirect('/app/estimates/' . $estimateId);
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $takeoff = $this->findOwned((int) $id);
        TakeoffMeasurement::query('DELETE FROM takeoff_measurements WHERE takeoff_id = ?', [$takeoff['id']]);
        if ($takeoff['plan_image_path']) {
            $file = BASE_PATH . '/public' . $takeoff['plan_image_path'];
            if (is_file($file)) {
                unlink($file);
            }
        }
        Takeoff::delete($takeoff['id']);
        $this->flash('success', 'Takeoff deleted.');
        self::redirect('/app/takeoffs');
    }

    private function findOwned(int $id): array
    {
        $takeoff = Takeoff::find($id);
        if (!$takeoff || (int) $takeoff['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Takeoff not found.');
        }
        return $takeoff;
    }

    private function jsonError(string $message): void
    {
        http_response_code(422);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $message]);
    }
}
