<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AsphaltEstimate;
use App\Models\AsphaltEstimateSegment;
use App\Models\Client;
use App\Models\Company;
use App\Models\CostCode;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Project;
use App\Models\TaxRate;
use App\Support\AsphaltEstimateCalc;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * "Asphalt / Road Estimate" tool for paving/road contractors — a parent
 * estimate (AsphaltEstimate) broken into one or more road segments
 * (AsphaltEstimateSegment), each with its own geometry and pavement-layer
 * structure. All quantity/cost math lives in App\Support\AsphaltEstimateCalc
 * (reused, never duplicated here); this controller's job is parsing the
 * repeatable segment_*[] form arrays (same parallel-array convention
 * EstimateController::store() uses for item_*[]), validating them, running
 * the calc, and persisting the parent + its segments. convertToEstimate()
 * turns a stored asphalt estimate into a real Estimate + EstimateItem rows,
 * tagged with the "32 10 00" (Paving) cost code — mirrors
 * QuickEstimateController::convertToEstimate() exactly.
 */
class AsphaltEstimateController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        $estimates = DB::table('asphalt_estimates as ae')
            ->leftJoin('projects as p', 'p.id', '=', 'ae.project_id')
            ->leftJoin('clients as c', 'c.id', '=', 'ae.client_id')
            ->where('ae.company_id', $companyId)
            ->orderByDesc('ae.created_at')
            ->select('ae.*', 'p.name as project_name', 'c.name as client_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.asphalt-estimates.index', [
            'estimates' => $estimates,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        return view('app.asphalt-estimates.create', [
            'estimate' => null,
            'segments' => [],
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'taxRates' => TaxRate::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'formAction' => '/app/asphalt-estimates',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $createUrl = '/app/asphalt-estimates/create';

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash($createUrl, 'error', t('user.asphalt_estimates.name_required'));
        }

        [$computedSegments, $rawSegments, $error] = $this->parseSegments($request);
        if ($error) {
            return $this->redirectWithFlash($createUrl, 'error', $error);
        }
        if (empty($computedSegments)) {
            return $this->redirectWithFlash($createUrl, 'error', t('user.asphalt_estimates.segments_required'));
        }

        [$otherCosts, $error] = $this->parseOtherCosts($request);
        if ($error) {
            return $this->redirectWithFlash($createUrl, 'error', $error);
        }

        $markupPercent = $this->validatedNumberOrDefault($request->input('markup_percent', 0), 0, 100, 0.0);
        if ($markupPercent === null) {
            return $this->redirectWithFlash($createUrl, 'error', t('user.asphalt_estimates.markup_invalid'));
        }
        $taxRate = $this->ownedTaxRate($request->input('tax_rate_id') ?: null, $companyId);
        $taxPercent = (float) ($taxRate->rate_percent ?? 0);

        $totals = AsphaltEstimateCalc::computeTotals($computedSegments, $otherCosts, $markupPercent, $taxPercent);

        $estimate = AsphaltEstimate::create([
            'company_id' => $companyId,
            'project_id' => $this->ownedProject($request->input('project_id') ?: null, $companyId)?->id,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'name' => mb_substr($name, 0, 150),
            'city' => trim((string) $request->input('city', '')) ?: null,
            'status' => 'draft',
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            ...$otherCosts,
            'markup_percent' => $markupPercent,
            'tax_rate_id' => $taxRate?->id,
            'tax_percent' => $taxPercent,
            'segments_subtotal' => $totals['segments_subtotal'],
            'other_costs_subtotal' => $totals['other_costs_subtotal'],
            'subtotal' => $totals['subtotal'],
            'markup_amount' => $totals['markup_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'total_asphalt_tons' => $totals['total_asphalt_tons'],
            'total_area_m2' => $totals['total_area_m2'],
        ]);

        $this->saveSegments($estimate->id, $rawSegments);

        $this->flash('success', t('user.asphalt_estimates.created'));
        return redirect('/app/asphalt-estimates/' . $estimate->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $project = $estimate->project_id ? $this->ownedProject($estimate->project_id, $estimate->company_id) : null;
        $client = $estimate->client_id ? $this->ownedClient($estimate->client_id, $estimate->company_id) : null;

        return view('app.asphalt-estimates.show', [
            'estimate' => $estimate->toArray(),
            'segments' => $estimate->segments()->get()->toArray(),
            'project' => $project?->toArray(),
            'client' => $client?->toArray(),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $companyId = $estimate->company_id;

        return view('app.asphalt-estimates.edit', [
            'estimate' => $estimate->toArray(),
            'segments' => $estimate->segments()->get()->toArray(),
            'projects' => Project::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'clients' => Client::where('company_id', $companyId)->orderBy('name')->get()->toArray(),
            'taxRates' => TaxRate::where('company_id', $companyId)->orderBy('sort_order')->orderBy('id')->get()->toArray(),
            'formAction' => '/app/asphalt-estimates/' . $estimate->id . '/update',
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $companyId = $estimate->company_id;
        $editUrl = '/app/asphalt-estimates/' . $estimate->id . '/edit';

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash($editUrl, 'error', t('user.asphalt_estimates.name_required'));
        }

        [$computedSegments, $rawSegments, $error] = $this->parseSegments($request);
        if ($error) {
            return $this->redirectWithFlash($editUrl, 'error', $error);
        }
        if (empty($computedSegments)) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.asphalt_estimates.segments_required'));
        }

        [$otherCosts, $error] = $this->parseOtherCosts($request);
        if ($error) {
            return $this->redirectWithFlash($editUrl, 'error', $error);
        }

        $markupPercent = $this->validatedNumberOrDefault($request->input('markup_percent', 0), 0, 100, 0.0);
        if ($markupPercent === null) {
            return $this->redirectWithFlash($editUrl, 'error', t('user.asphalt_estimates.markup_invalid'));
        }
        $taxRate = $this->ownedTaxRate($request->input('tax_rate_id') ?: null, $companyId);
        $taxPercent = (float) ($taxRate->rate_percent ?? 0);

        $totals = AsphaltEstimateCalc::computeTotals($computedSegments, $otherCosts, $markupPercent, $taxPercent);

        $estimate->update([
            'project_id' => $this->ownedProject($request->input('project_id') ?: null, $companyId)?->id,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'name' => mb_substr($name, 0, 150),
            'city' => trim((string) $request->input('city', '')) ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            ...$otherCosts,
            'markup_percent' => $markupPercent,
            'tax_rate_id' => $taxRate?->id,
            'tax_percent' => $taxPercent,
            'segments_subtotal' => $totals['segments_subtotal'],
            'other_costs_subtotal' => $totals['other_costs_subtotal'],
            'subtotal' => $totals['subtotal'],
            'markup_amount' => $totals['markup_amount'],
            'tax_amount' => $totals['tax_amount'],
            'total' => $totals['total'],
            'total_asphalt_tons' => $totals['total_asphalt_tons'],
            'total_area_m2' => $totals['total_area_m2'],
        ]);

        // Segments are always replaced wholesale rather than diffed/patched in
        // place — same spirit as AssemblyController::destroy() deleting every
        // AssemblyItem row rather than reconciling individual ones.
        AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->delete();
        $this->saveSegments($estimate->id, $rawSegments);

        $this->flash('success', t('user.asphalt_estimates.updated'));
        return redirect('/app/asphalt-estimates/' . $estimate->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        AsphaltEstimateSegment::where('asphalt_estimate_id', $estimate->id)->delete();
        $estimate->delete();

        $this->flash('success', t('user.asphalt_estimates.deleted'));
        return redirect('/app/asphalt-estimates');
    }

    /**
     * Converts a stored asphalt estimate into a real Estimate: one
     * EstimateItem per segment per non-zero pavement layer, plus one each
     * for the non-zero "other cost" lines, every one tagged with the
     * "32 10 00" (Paving — Roads & Parking) cost code. Subtotal/markup/tax/
     * total are copied straight from the asphalt estimate's own cached
     * totals — mirrors QuickEstimateController::convertToEstimate() exactly.
     */
    public function convertToEstimate(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('asphalt_estimate')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $estimate = $this->findOwned($id);
        $companyId = $estimate->company_id;
        $segments = $estimate->segments()->get();
        $costCodeId = CostCode::where('company_id', null)->where('code', '32 10 00')->first()?->id;

        $company = Company::find($companyId);
        $approvalFields = ($company && $company->requiresEstimateApproval())
            ? ['approval_status' => 'pending', 'approval_requested_by' => Auth::id(), 'approval_requested_at' => now()]
            : [];

        $newEstimate = Estimate::create([
            'company_id' => $companyId,
            'project_id' => $estimate->project_id,
            'client_id' => $estimate->client_id,
            'title' => $estimate->name,
            'status' => 'draft',
            'subtotal' => $estimate->subtotal,
            'markup_percent' => $estimate->markup_percent,
            'markup_amount' => $estimate->markup_amount,
            'tax_rate_id' => $estimate->tax_rate_id,
            'tax_percent' => $estimate->tax_percent,
            'tax_amount' => $estimate->tax_amount,
            'total' => $estimate->total,
            'share_token' => bin2hex(random_bytes(20)),
            'valid_until' => now()->addDays(30)->toDateString(),
            ...$approvalFields,
        ]);

        foreach ($segments as $segment) {
            $this->createLayerItem($newEstimate->id, $costCodeId, (float) $segment->subbase_cost, [
                'description' => "{$segment->name} — " . t('user.asphalt_estimates.layer_subbase') . " ({$segment->subbase_thickness_mm}mm, {$segment->subbase_volume_m3} m³)",
                'qty' => (float) $segment->subbase_volume_m3,
                'uom' => 'm3',
                'unit_cost' => (float) $segment->subbase_unit_cost_per_m3,
                'total' => (float) $segment->subbase_cost,
            ]);
            $this->createLayerItem($newEstimate->id, $costCodeId, (float) $segment->base_course_cost, [
                'description' => "{$segment->name} — " . t('user.asphalt_estimates.layer_base_course') . " ({$segment->base_course_thickness_mm}mm, {$segment->base_course_volume_m3} m³)",
                'qty' => (float) $segment->base_course_volume_m3,
                'uom' => 'm3',
                'unit_cost' => (float) $segment->base_course_unit_cost_per_m3,
                'total' => (float) $segment->base_course_cost,
            ]);
            $asphaltThickness = (float) $segment->asphalt_binder_thickness_mm + (float) $segment->asphalt_wearing_thickness_mm;
            $this->createLayerItem($newEstimate->id, $costCodeId, (float) $segment->asphalt_cost, [
                'description' => "{$segment->name} — " . t('user.asphalt_estimates.layer_asphalt') . " ({$asphaltThickness}mm, {$segment->asphalt_tons} t)",
                'qty' => (float) $segment->asphalt_tons,
                'uom' => 'ton',
                'unit_cost' => (float) $segment->asphalt_unit_cost_per_ton,
                'total' => (float) $segment->asphalt_cost,
            ]);
        }

        $workerDays = (float) $estimate->labor_crew_size * (float) $estimate->labor_days;
        $otherLines = [
            ['total' => (float) $estimate->total_asphalt_tons * (float) $estimate->transport_cost_per_ton, 'description' => t('user.asphalt_estimates.line_transport'), 'qty' => (float) $estimate->total_asphalt_tons, 'uom' => 'ton', 'unit_cost' => (float) $estimate->transport_cost_per_ton],
            ['total' => (float) $estimate->paver_daily_rate * (float) $estimate->paver_days, 'description' => t('user.asphalt_estimates.line_paver'), 'qty' => (float) $estimate->paver_days, 'uom' => 'day', 'unit_cost' => (float) $estimate->paver_daily_rate],
            ['total' => (float) $estimate->roller_daily_rate * (float) $estimate->roller_days, 'description' => t('user.asphalt_estimates.line_roller'), 'qty' => (float) $estimate->roller_days, 'uom' => 'day', 'unit_cost' => (float) $estimate->roller_daily_rate],
            ['total' => $workerDays * (float) $estimate->labor_daily_rate_per_worker, 'description' => t('user.asphalt_estimates.line_labor'), 'qty' => $workerDays, 'uom' => 'worker-day', 'unit_cost' => (float) $estimate->labor_daily_rate_per_worker],
            ['total' => $workerDays * (float) $estimate->labor_food_daily_rate_per_worker, 'description' => t('user.asphalt_estimates.line_labor_food'), 'qty' => $workerDays, 'uom' => 'worker-day', 'unit_cost' => (float) $estimate->labor_food_daily_rate_per_worker],
            ['total' => (float) $estimate->surveyor_fee, 'description' => t('user.asphalt_estimates.line_surveyor'), 'qty' => 1, 'uom' => 'each', 'unit_cost' => (float) $estimate->surveyor_fee],
            ['total' => (float) $estimate->lab_testing_fee, 'description' => t('user.asphalt_estimates.line_lab_testing'), 'qty' => 1, 'uom' => 'each', 'unit_cost' => (float) $estimate->lab_testing_fee],
            ['total' => (float) $estimate->mobilization_fee, 'description' => t('user.asphalt_estimates.line_mobilization'), 'qty' => 1, 'uom' => 'each', 'unit_cost' => (float) $estimate->mobilization_fee],
        ];
        foreach ($otherLines as $line) {
            $this->createLayerItem($newEstimate->id, $costCodeId, $line['total'], $line);
        }

        $this->flash('success', t('user.asphalt_estimates.converted'));
        return redirect('/app/estimates/' . $newEstimate->id);
    }

    /** Creates one EstimateItem, but only when $total (the line's cost) is non-zero — a $0 layer/other-cost line never clutters the converted estimate. */
    private function createLayerItem(int $estimateId, ?int $costCodeId, float $total, array $line): void
    {
        if (round($total, 2) <= 0.0) {
            return;
        }
        EstimateItem::create([
            'estimate_id' => $estimateId,
            'description' => $line['description'],
            'qty' => $line['qty'],
            'uom' => $line['uom'],
            'unit_cost' => $line['unit_cost'],
            'total' => round($total, 2),
            'cost_code_id' => $costCodeId,
        ]);
    }

    /**
     * Parses the repeatable segment_*[] arrays (same parallel-array
     * convention as EstimateController::store()'s item_*[]). A row whose
     * name is blank is silently skipped (an unused placeholder row left by
     * the repeatable-row UI, same as a blank item_description[] row).
     * length_m/width_m must be present and > 0 for every non-blank row, or
     * the whole request is rejected — every other field defaults to 0 when
     * blank/missing, since a zero-thickness layer is a legitimate "this
     * segment has no base course yet" input, not an error.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: ?string} [computedSegments, rawSegments, error]
     */
    private function parseSegments(Request $request): array
    {
        $names = (array) $request->input('segment_name', []);
        $lengths = (array) $request->input('segment_length_m', []);
        $widths = (array) $request->input('segment_width_m', []);
        $medians = (array) $request->input('segment_median_width_m', []);
        $subbaseThickness = (array) $request->input('segment_subbase_thickness_mm', []);
        $subbaseUnitCost = (array) $request->input('segment_subbase_unit_cost_per_m3', []);
        $baseThickness = (array) $request->input('segment_base_course_thickness_mm', []);
        $baseUnitCost = (array) $request->input('segment_base_course_unit_cost_per_m3', []);
        $binderThickness = (array) $request->input('segment_asphalt_binder_thickness_mm', []);
        $wearingThickness = (array) $request->input('segment_asphalt_wearing_thickness_mm', []);
        $density = (array) $request->input('segment_asphalt_density_kg_per_m3', []);
        $asphaltUnitCost = (array) $request->input('segment_asphalt_unit_cost_per_ton', []);

        $computed = [];
        $raw = [];
        $sortOrder = 0;
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $lengthM = $this->validatedNumber($lengths[$i] ?? null, 0.01, 999999.99);
            if ($lengthM === null) {
                return [[], [], t('user.asphalt_estimates.segment_length_invalid', ['name' => $name])];
            }
            $widthM = $this->validatedNumber($widths[$i] ?? null, 0.01, 9999.99);
            if ($widthM === null) {
                return [[], [], t('user.asphalt_estimates.segment_width_invalid', ['name' => $name])];
            }

            // [value, min, max, default-when-blank] — every layer field is optional
            // and defaults to 0 (a legitimate "this segment has no such layer"
            // input), except density, whose real-world default is 2400 kg/m³.
            $numericFields = [
                'median_width_m' => [$medians[$i] ?? 0, 0, 9999.99, 0.0],
                'subbase_thickness_mm' => [$subbaseThickness[$i] ?? 0, 0, 9999.99, 0.0],
                'subbase_unit_cost_per_m3' => [$subbaseUnitCost[$i] ?? 0, 0, 999999.99, 0.0],
                'base_course_thickness_mm' => [$baseThickness[$i] ?? 0, 0, 9999.99, 0.0],
                'base_course_unit_cost_per_m3' => [$baseUnitCost[$i] ?? 0, 0, 999999.99, 0.0],
                'asphalt_binder_thickness_mm' => [$binderThickness[$i] ?? 0, 0, 9999.99, 0.0],
                'asphalt_wearing_thickness_mm' => [$wearingThickness[$i] ?? 0, 0, 9999.99, 0.0],
                'asphalt_density_kg_per_m3' => [$density[$i] ?? 2400, 0.01, 9999.99, 2400.0],
                'asphalt_unit_cost_per_ton' => [$asphaltUnitCost[$i] ?? 0, 0, 999999.99, 0.0],
            ];
            $segment = ['length_m' => $lengthM, 'width_m' => $widthM];
            foreach ($numericFields as $field => [$value, $min, $max, $default]) {
                $parsed = $this->validatedNumberOrDefault($value, $min, $max, $default);
                if ($parsed === null) {
                    return [[], [], t('user.asphalt_estimates.segment_field_invalid', ['name' => $name])];
                }
                $segment[$field] = $parsed;
            }

            $computedSegment = AsphaltEstimateCalc::computeSegment($segment);
            $computed[] = $computedSegment;
            $raw[] = [
                'name' => mb_substr($name, 0, 150),
                'sort_order' => $sortOrder++,
                ...$segment,
                ...$computedSegment,
            ];
        }

        return [$computed, $raw, null];
    }

    /**
     * Parses the non-segment "other cost" inputs — every field is optional
     * and defaults to 0 when blank/missing, capped well above anything a
     * real project would need so a crafted huge value can't overflow the
     * decimal columns it's eventually stored in.
     *
     * @return array{0: array<string, float|int>, 1: ?string} [otherCosts, error]
     */
    private function parseOtherCosts(Request $request): array
    {
        $fields = [
            'transport_cost_per_ton' => 999999.99,
            'paver_daily_rate' => 999999.99,
            'paver_days' => 9999.99,
            'roller_daily_rate' => 999999.99,
            'roller_days' => 9999.99,
            'labor_days' => 9999.99,
            'labor_daily_rate_per_worker' => 999999.99,
            'labor_food_daily_rate_per_worker' => 999999.99,
            'surveyor_fee' => 9999999.99,
            'lab_testing_fee' => 9999999.99,
            'mobilization_fee' => 9999999.99,
        ];
        $otherCosts = [];
        foreach ($fields as $field => $max) {
            $value = $this->validatedNumberOrDefault($request->input($field, 0), 0, $max, 0.0);
            if ($value === null) {
                return [[], t('user.asphalt_estimates.other_cost_invalid')];
            }
            $otherCosts[$field] = $value;
        }

        $crewSize = $request->input('labor_crew_size', 0);
        if ($crewSize === null || $crewSize === '') {
            $otherCosts['labor_crew_size'] = 0;
        } elseif (!is_numeric($crewSize) || (int) $crewSize < 0 || (int) $crewSize > 9999 || (float) $crewSize != (int) $crewSize) {
            return [[], t('user.asphalt_estimates.crew_size_invalid')];
        } else {
            $otherCosts['labor_crew_size'] = (int) $crewSize;
        }

        return [$otherCosts, null];
    }

    /** @param  array<int, array<string, mixed>>  $rawSegments  each entry already carries both its raw inputs and AsphaltEstimateCalc::computeSegment()'s cached outputs, merged by parseSegments(). */
    private function saveSegments(int $estimateId, array $rawSegments): void
    {
        foreach ($rawSegments as $segment) {
            AsphaltEstimateSegment::create(['asphalt_estimate_id' => $estimateId, ...$segment]);
        }
    }

    /** Only returns the estimate if it belongs to the authenticated user's company — never trust a raw route-param id. */
    private function findOwned(int $id): AsphaltEstimate
    {
        $estimate = AsphaltEstimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== Auth::user()->company_id, 404, 'Asphalt estimate not found.');
        return $estimate;
    }

    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;
        return ($float >= $min && $float <= $max) ? $float : null;
    }

    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }
        return $this->validatedNumber($value, $min, $max);
    }

    /** Only returns the client if it belongs to $companyId — never leak another company's contact data via a foreign key. */
    private function ownedClient(?int $id, int $companyId): ?Client
    {
        if (!$id) {
            return null;
        }
        $client = Client::find($id);
        return ($client && $client->company_id === $companyId) ? $client : null;
    }

    /** Only returns the project if it belongs to $companyId — never let an asphalt estimate link to another company's project via a tampered id. */
    private function ownedProject(?int $id, int $companyId): ?Project
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project : null;
    }

    private function ownedTaxRate(?int $id, int $companyId): ?TaxRate
    {
        if (!$id) {
            return null;
        }
        $taxRate = TaxRate::find($id);
        return ($taxRate && $taxRate->company_id === $companyId) ? $taxRate : null;
    }
}
