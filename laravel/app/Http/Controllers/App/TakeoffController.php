<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Material;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Takeoff;
use App\Models\TakeoffMeasurement;
use App\Support\AiTakeoffAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TakeoffController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $takeoffs = DB::table('takeoffs as t')
            ->leftJoin('projects as p', 'p.id', '=', 't.project_id')
            ->where('t.company_id', $companyId)
            ->orderByDesc('t.created_at')
            ->select('t.*', 'p.name as project_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.takeoffs.index', ['takeoffs' => $takeoffs]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        return view('app.takeoffs.create', [
            'projects' => Project::where('company_id', Auth::user()->company_id)->orderBy('name')->get()->toArray(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $name = trim((string) $request->input('name'));

        if ($name === '') {
            return $this->redirectWithFlash('/app/takeoffs/create', 'error', 'Takeoff name is required.');
        }

        $imagePath = null;
        $planImage = $request->file('plan_image');
        if ($planImage && $planImage->isValid()) {
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $mime = $planImage->getMimeType();
            if (!isset($allowed[$mime])) {
                return $this->redirectWithFlash('/app/takeoffs/create', 'error', 'Plan image must be a JPG, PNG, or WEBP file.');
            }
            if ($planImage->getSize() > 8 * 1024 * 1024) {
                return $this->redirectWithFlash('/app/takeoffs/create', 'error', 'Plan image must be smaller than 8MB.');
            }
            $filename = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
            $planImage->move(public_path("uploads/takeoffs/{$companyId}"), $filename);
            $imagePath = "/uploads/takeoffs/{$companyId}/{$filename}";
        }

        $projectId = $request->input('project_id') ?: null;
        if ($projectId) {
            $project = Project::find((int) $projectId);
            if (!$project || $project->company_id !== $companyId) {
                $projectId = null;
            }
        }

        $takeoff = Takeoff::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'name' => $name,
            'plan_image_path' => $imagePath,
            'scale_px_per_unit' => 1,
            'scale_unit' => 'm',
        ]);

        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $measurements = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->orderBy('id')->get()->toArray();
        $totalCost = array_sum(array_map(fn ($m) => (float) $m['total_cost'], $measurements));
        $materials = DB::table('materials as m')
            ->leftJoin('suppliers as s', 's.id', '=', 'm.supplier_id')
            ->where('m.company_id', $takeoff->company_id)
            ->orderBy('m.category')->orderBy('m.name')
            ->select('m.*', 's.name as supplier_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.takeoffs.show', [
            'takeoff' => $takeoff->toArray(),
            'measurements' => $measurements,
            'totalCost' => $totalCost,
            'materials' => $materials,
            'aiConfigured' => AiTakeoffAnalyzer::isConfigured(),
        ]);
    }

    public function calibrate(Request $request, int $id): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);

        $pixelDistance = (float) $request->input('pixel_distance', 0);
        $realLength = (float) $request->input('real_length', 0);

        if ($pixelDistance <= 0 || $realLength <= 0) {
            return response()->json(['ok' => false, 'error' => 'Invalid calibration values.'], 422);
        }

        $takeoff->update(['scale_px_per_unit' => $pixelDistance / $realLength]);
        return response()->json(['ok' => true, 'scale' => $pixelDistance / $realLength]);
    }

    public function addMeasurement(Request $request, int $id): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);

        $type = (string) $request->input('type');
        $label = trim((string) $request->input('label')) ?: ucfirst($type);
        $value = (float) $request->input('value', 0);
        $unitCost = (float) $request->input('unit_cost', 0);
        $points = (string) $request->input('points', '[]');

        if (!in_array($type, ['length', 'area', 'count'], true) || $value <= 0) {
            return response()->json(['ok' => false, 'error' => 'Invalid measurement.'], 422);
        }

        $unit = $type === 'length' ? $takeoff->scale_unit : ($type === 'area' ? $takeoff->scale_unit . '²' : 'ea');

        $measurement = TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'type' => $type,
            'label' => $label,
            'points_json' => $points,
            'value' => $value,
            'unit' => $unit,
            'unit_cost' => $unitCost,
            'total_cost' => $value * $unitCost,
        ]);

        return response()->json(['ok' => true, 'id' => $measurement->id]);
    }

    /**
     * Runs the plan image through Claude's vision support and creates a first-pass
     * TakeoffMeasurement row for each item it identifies — plain "length"/"area"/"count"
     * rows, indistinguishable from manually-drawn ones, so the contractor reviews and
     * edits them with the same tools used in show.blade.php.
     */
    public function aiAnalyze(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);

        if (!$takeoff->plan_image_path) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', 'Upload a plan image before running AI analysis.');
        }
        if (!AiTakeoffAnalyzer::isConfigured()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', 'AI analysis isn\'t configured yet. Ask your platform admin to enable it under Admin > Platform Settings > AI Generator.');
        }

        $items = AiTakeoffAnalyzer::analyzeTakeoffPlan($takeoff->plan_image_path);

        if ($items === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', 'AI analysis failed: ' . $lastError);
        }
        if (empty($items)) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', 'AI analysis did not identify any measurements on this plan — try measuring manually instead.');
        }

        $materials = Material::where('company_id', $takeoff->company_id)->get(['name', 'category', 'unit_cost']);

        $count = 0;
        foreach ($items as $item) {
            $type = $item['type'];
            $unit = $type === 'length' ? $takeoff->scale_unit : ($type === 'area' ? $takeoff->scale_unit . '²' : 'ea');
            $label = $item['label'];
            if ($item['note'] !== '') {
                $label .= ' (AI: ' . $item['note'] . ')';
            }
            $label = mb_substr($label, 0, 150);
            $unitCost = $this->suggestUnitCost($item['label'], $materials);

            TakeoffMeasurement::create([
                'takeoff_id' => $takeoff->id,
                'type' => $type,
                'label' => $label,
                'points_json' => '[]',
                'value' => $item['value'],
                'unit' => $unit,
                'unit_cost' => $unitCost,
                'total_cost' => $item['value'] * $unitCost,
            ]);
            $count++;
        }

        $this->flash('success', "{$count} measurement(s) suggested by AI — review and adjust before converting to an estimate.");
        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    /** Cheap case-insensitive substring match between the AI's label and the company's material library — 0 when nothing matches, so the user fills it in themselves. */
    private function suggestUnitCost(string $label, $materials): float
    {
        $needle = strtolower(trim($label));
        if ($needle === '') {
            return 0.0;
        }
        foreach ($materials as $m) {
            $name = strtolower((string) $m->name);
            $category = strtolower((string) $m->category);
            if ($name !== '' && (str_contains($needle, $name) || str_contains($name, $needle))) {
                return (float) $m->unit_cost;
            }
            if ($category !== '' && str_contains($needle, $category)) {
                return (float) $m->unit_cost;
            }
        }
        return 0.0;
    }

    public function deleteMeasurement(int $id, int $measurementId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $measurement = TakeoffMeasurement::find($measurementId);
        if ($measurement && $measurement->takeoff_id === $takeoff->id) {
            $measurement->delete();
        }
        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    public function convertToEstimate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $measurements = TakeoffMeasurement::where('takeoff_id', $takeoff->id)->orderBy('id')->get();

        if ($measurements->isEmpty()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', 'Add at least one measurement before converting to an estimate.');
        }

        $total = (float) $measurements->sum('total_cost');

        $estimate = Estimate::create([
            'company_id' => $takeoff->company_id,
            'project_id' => $takeoff->project_id,
            'client_id' => null,
            'title' => $takeoff->name . ' — Takeoff Estimate',
            'status' => 'draft',
            'total' => $total,
        ]);

        foreach ($measurements as $m) {
            EstimateItem::create([
                'estimate_id' => $estimate->id,
                'description' => $m->label . ' (' . number_format((float) $m->value, 2) . ' ' . $m->unit . ')',
                'qty' => $m->value,
                'unit_cost' => $m->unit_cost,
                'total' => $m->total_cost,
            ]);
        }

        $this->flash('success', 'Takeoff converted to a new estimate.');
        return redirect('/app/estimates/' . $estimate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        TakeoffMeasurement::where('takeoff_id', $takeoff->id)->delete();
        if ($takeoff->plan_image_path) {
            $file = public_path($takeoff->plan_image_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $takeoff->delete();
        $this->flash('success', 'Takeoff deleted.');
        return redirect('/app/takeoffs');
    }

    private function findOwned(int $id): Takeoff
    {
        $takeoff = Takeoff::find($id);
        abort_if(!$takeoff || $takeoff->company_id !== Auth::user()->company_id, 404, 'Takeoff not found.');
        return $takeoff;
    }
}
