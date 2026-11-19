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
use App\Models\TakeoffPage;
use App\Support\AiTakeoffAnalyzer;
use App\Support\TakeoffPdfConverter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TakeoffController extends Controller
{
    private const ALLOWED_IMAGE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

    private const MAX_PDF_BYTES = 20 * 1024 * 1024;

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

    /**
     * Accepts either a single image (wrapped as one page, exactly like before this takeoff
     * tool supported multi-page plan sets) or a PDF of up to TakeoffPdfConverter::MAX_PAGES
     * pages (converted server-side into one page per sheet). Either way, every page this
     * takeoff ends up with is a TakeoffPage row — takeoffs.plan_image_path etc. are left null
     * for a takeoff created here; see Takeoff's own docblock.
     */
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
            return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.name_required'));
        }

        $uploaded = $request->file('plan_image');
        $isPdf = false;

        if ($uploaded && $uploaded->isValid()) {
            $mime = $uploaded->getMimeType();
            if ($mime === 'application/pdf' || strtolower((string) $uploaded->getClientOriginalExtension()) === 'pdf') {
                $isPdf = true;
                if ($uploaded->getSize() > self::MAX_PDF_BYTES) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_max_size'));
                }
                if (!TakeoffPdfConverter::isAvailable()) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_conversion_unavailable'));
                }
            } elseif (isset(self::ALLOWED_IMAGE_MIMES[$mime])) {
                if ($uploaded->getSize() > self::MAX_IMAGE_BYTES) {
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.image_max_size'));
                }
            } else {
                return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.image_type_invalid'));
            }
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
            'name' => mb_substr($name, 0, 150),
        ]);

        if ($uploaded && $uploaded->isValid()) {
            $destDir = public_path("uploads/takeoffs/{$companyId}/{$takeoff->id}");

            if ($isPdf) {
                $result = TakeoffPdfConverter::convert($uploaded->getRealPath(), $destDir);
                if (!$result['success']) {
                    $takeoff->delete();
                    @rmdir($destDir);
                    if ($result['error'] === 'too_many_pages') {
                        return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_too_many_pages', ['max' => TakeoffPdfConverter::MAX_PAGES]));
                    }
                    return $this->redirectWithFlash('/app/takeoffs/create', 'error', t('user.takeoffs.pdf_conversion_failed'));
                }

                $pageNumber = 1;
                foreach ($result['files'] as $filename) {
                    TakeoffPage::create([
                        'takeoff_id' => $takeoff->id,
                        'page_number' => $pageNumber,
                        'image_path' => "/uploads/takeoffs/{$companyId}/{$takeoff->id}/{$filename}",
                        'scale_px_per_unit' => 1,
                        'scale_unit' => 'm',
                    ]);
                    $pageNumber++;
                }
            } else {
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                $ext = self::ALLOWED_IMAGE_MIMES[$uploaded->getMimeType()];
                $filename = 'page-1.' . $ext;
                $uploaded->move($destDir, $filename);

                TakeoffPage::create([
                    'takeoff_id' => $takeoff->id,
                    'page_number' => 1,
                    'image_path' => "/uploads/takeoffs/{$companyId}/{$takeoff->id}/{$filename}",
                    'scale_px_per_unit' => 1,
                    'scale_unit' => 'm',
                ]);
            }
        }

        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    /**
     * Renders the canvas for one page of the takeoff — the one named by ?page_id=, or the
     * first page when that's missing/invalid/absent (which is every single-image takeoff,
     * since it only ever has one page). The measurements list and its total are scoped to
     * that one page; grandTotalCost is the whole takeoff's total across every page, for the
     * page picker / convert-to-estimate context.
     */
    public function show(Request $request, int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->orderBy('page_number')->get();

        $currentPage = null;
        $requestedPageId = (int) $request->query('page_id', 0);
        if ($requestedPageId) {
            $currentPage = $pages->firstWhere('id', $requestedPageId);
        }
        if (!$currentPage) {
            $currentPage = $pages->first();
        }

        $measurements = $currentPage
            ? TakeoffMeasurement::where('takeoff_page_id', $currentPage->id)->orderBy('id')->get()->toArray()
            : [];
        $totalCost = array_sum(array_map(fn ($m) => (float) $m['total_cost'], $measurements));
        $grandTotalCost = (float) TakeoffMeasurement::where('takeoff_id', $takeoff->id)->sum('total_cost');

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
            'pages' => $pages->toArray(),
            'currentPage' => $currentPage?->toArray(),
            'measurements' => $measurements,
            'totalCost' => $totalCost,
            'grandTotalCost' => $grandTotalCost,
            'materials' => $materials,
            'aiConfigured' => AiTakeoffAnalyzer::isConfigured(),
        ]);
    }

    public function calibrate(Request $request, int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        $pixelDistance = $this->validatedNumber($request->input('pixel_distance', 0), 0.01, 999999999);
        $realLength = $this->validatedNumber($request->input('real_length', 0), 0.01, 999999999);

        if ($pixelDistance === null || $realLength === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid calibration values.'], 422);
        }

        $page->update(['scale_px_per_unit' => $pixelDistance / $realLength]);
        return response()->json(['ok' => true, 'scale' => $pixelDistance / $realLength]);
    }

    public function addMeasurement(Request $request, int $id, int $pageId): JsonResponse
    {
        if ($this->requireFeature('takeoff')) {
            return response()->json(['ok' => false, 'error' => 'Not available on your plan.'], 403);
        }
        if ($this->requireAbility('write')) {
            return response()->json(['ok' => false, 'error' => 'Not permitted.'], 403);
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);

        $type = (string) $request->input('type');
        $label = mb_substr(trim((string) $request->input('label')) ?: ucfirst($type), 0, 150);
        $value = $this->validatedNumber($request->input('value', 0), 0.001, 999999999.999);
        $unitCost = $this->validatedNumberOrDefault($request->input('unit_cost', 0), 0, 99999999.99, 0.0);
        $points = (string) $request->input('points', '[]');

        if (!in_array($type, ['length', 'area', 'count'], true) || $value === null || $unitCost === null) {
            return response()->json(['ok' => false, 'error' => 'Invalid measurement.'], 422);
        }

        $unit = $type === 'length' ? $page->scale_unit : ($type === 'area' ? $page->scale_unit . '²' : 'ea');

        $measurement = TakeoffMeasurement::create([
            'takeoff_id' => $takeoff->id,
            'takeoff_page_id' => $page->id,
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
     * Runs one page's image through Claude's vision support and creates a first-pass
     * TakeoffMeasurement row for each item it identifies — plain "length"/"area"/"count"
     * rows, indistinguishable from manually-drawn ones, so the contractor reviews and
     * edits them with the same tools used in show.blade.php.
     */
    public function aiAnalyze(int $id, int $pageId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $page = $this->findOwnedPage($takeoff, $pageId);
        $redirectUrl = '/app/takeoffs/' . $takeoff->id . '?page_id=' . $page->id;

        if (!AiTakeoffAnalyzer::isConfigured()) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_not_configured'));
        }

        $items = AiTakeoffAnalyzer::analyzeTakeoffPlan($page->image_path);

        if ($items === null) {
            $lastError = Setting::get('ai_last_error') ?: 'The AI provider did not return a usable response.';
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_failed', ['reason' => $lastError]));
        }
        if (empty($items)) {
            return $this->redirectWithFlash($redirectUrl, 'error', t('user.takeoffs.ai_no_measurements'));
        }

        $count = $this->createMeasurementsFromAi($takeoff, $page, $items);

        $this->flash('success', t('user.takeoffs.ai_measurements_suggested', ['count' => $count]));
        return redirect($redirectUrl);
    }

    /**
     * Same analysis as aiAnalyze(), run sequentially across every page in the set, with each
     * detected item's label tagged with the page it came from so a 10+ page review list
     * stays legible. A page whose analysis fails outright (AiTakeoffAnalyzer returns null)
     * is skipped rather than aborting the rest of the set.
     */
    public function aiAnalyzeAll(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('takeoff')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $takeoff = $this->findOwned($id);
        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->orderBy('page_number')->get();

        if ($pages->isEmpty()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.image_required_for_ai'));
        }
        if (!AiTakeoffAnalyzer::isConfigured()) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.ai_not_configured'));
        }

        $totalCount = 0;
        $failures = 0;
        foreach ($pages as $page) {
            $items = AiTakeoffAnalyzer::analyzeTakeoffPlan($page->image_path);
            if ($items === null || empty($items)) {
                $failures++;
                continue;
            }
            $totalCount += $this->createMeasurementsFromAi($takeoff, $page, $items, true);
        }

        if ($totalCount === 0) {
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.ai_no_measurements'));
        }

        $message = $failures > 0
            ? t('user.takeoffs.ai_measurements_suggested_partial', ['count' => $totalCount, 'failed' => $failures])
            : t('user.takeoffs.ai_measurements_suggested', ['count' => $totalCount]);

        $this->flash('success', $message);
        return redirect('/app/takeoffs/' . $takeoff->id);
    }

    /** Shared by aiAnalyze() and aiAnalyzeAll() — creates one TakeoffMeasurement per detected item on $page. */
    private function createMeasurementsFromAi(Takeoff $takeoff, TakeoffPage $page, array $items, bool $tagPageInLabel = false): int
    {
        $materials = Material::where('company_id', $takeoff->company_id)->get(['name', 'category', 'unit_cost']);

        $count = 0;
        foreach ($items as $item) {
            $type = $item['type'];
            $unit = $type === 'length' ? $page->scale_unit : ($type === 'area' ? $page->scale_unit . '²' : 'ea');
            $label = $item['label'];
            if ($tagPageInLabel) {
                $label = t('user.takeoffs.page_label', ['number' => $page->page_number]) . ': ' . $label;
            }
            if ($item['note'] !== '') {
                $label .= ' (AI: ' . $item['note'] . ')';
            }
            $label = mb_substr($label, 0, 150);
            $unitCost = $this->suggestUnitCost($item['label'], $materials);

            TakeoffMeasurement::create([
                'takeoff_id' => $takeoff->id,
                'takeoff_page_id' => $page->id,
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
        return $count;
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
        $redirectUrl = '/app/takeoffs/' . $takeoff->id;
        if ($measurement && $measurement->takeoff_id === $takeoff->id) {
            // Redirect back to the page the deleted measurement belonged to, so the user
            // isn't bounced back to page 1 while reviewing a later page's measurements.
            if ($measurement->takeoff_page_id) {
                $redirectUrl .= '?page_id=' . $measurement->takeoff_page_id;
            }
            $measurement->delete();
        }
        return redirect($redirectUrl);
    }

    /** Pulls every measurement across every page of the takeoff — a measurement's takeoff_id is shared by all of its pages, so this already spans the whole multi-page set with no change needed. */
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
            return $this->redirectWithFlash('/app/takeoffs/' . $takeoff->id, 'error', t('user.takeoffs.measurement_required_for_conversion'));
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

        $this->flash('success', t('user.takeoffs.converted'));
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

        $pages = TakeoffPage::where('takeoff_id', $takeoff->id)->get();
        foreach ($pages as $page) {
            $file = public_path($page->image_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        TakeoffPage::where('takeoff_id', $takeoff->id)->delete();

        // Vestigial legacy column — covered by a backfilled page for any takeoff that went
        // through the takeoff_pages migration, but this keeps destroy() correct even if an
        // old row somehow has no page of its own.
        if ($takeoff->plan_image_path) {
            $file = public_path($takeoff->plan_image_path);
            if (is_file($file)) {
                unlink($file);
            }
        }

        $companyTakeoffDir = public_path("uploads/takeoffs/{$takeoff->company_id}/{$takeoff->id}");
        if (is_dir($companyTakeoffDir)) {
            @rmdir($companyTakeoffDir); // only succeeds once empty
        }

        $takeoff->delete();
        $this->flash('success', t('user.takeoffs.deleted'));
        return redirect('/app/takeoffs');
    }

    private function findOwned(int $id): Takeoff
    {
        $takeoff = Takeoff::find($id);
        abort_if(!$takeoff || $takeoff->company_id !== Auth::user()->company_id, 404, 'Takeoff not found.');
        return $takeoff;
    }

    private function findOwnedPage(Takeoff $takeoff, int $pageId): TakeoffPage
    {
        $page = TakeoffPage::find($pageId);
        abort_if(!$page || $page->takeoff_id !== $takeoff->id, 404, 'Takeoff page not found.');
        return $page;
    }

    /** Rejects a non-numeric or out-of-range value — see EstimateController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }
}
