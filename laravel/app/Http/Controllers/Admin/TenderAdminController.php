<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Admin-curated global feed of government/giga-project tender opportunities — see Tender model. */
class TenderAdminController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.tenders.index', [
            'tenders' => Tender::orderBy('submission_deadline')->orderBy('sort_order')->paginate($this->perPage($request))->withQueryString(),
            'categories' => Tender::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.tenders.form', ['tender' => null, 'categories' => Tender::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $this->collectInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/tenders/create', 'error', $input);
        }

        $tender = Tender::create($input + ['is_active' => true]);
        $this->flash('success', t('admin.tenders.added'));

        return redirect('/admin/tenders/'.$tender->id.'/edit');
    }

    public function edit(int $id): View
    {
        return view('admin.tenders.form', ['tender' => Tender::findOrFail($id)->toArray(), 'categories' => Tender::TYPES]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tender = Tender::findOrFail($id);

        $input = $this->collectInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/tenders/'.$tender->id.'/edit', 'error', $input);
        }

        $tender->update($input + [
            'is_active' => (bool) $request->input('is_active'),
        ]);
        $this->flash('success', t('admin.tenders.updated'));

        return redirect('/admin/tenders/'.$tender->id.'/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        Tender::findOrFail($id)->delete();

        return $this->redirectWithFlash('/admin/tenders', 'success', t('admin.tenders.deleted'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectInput(Request $request): array|string
    {
        $titleEn = trim((string) $request->input('title_en'));
        if ($titleEn === '' || mb_strlen($titleEn) > 255) {
            return t('admin.tenders.title_required');
        }
        $titleAr = trim((string) $request->input('title_ar', ''));
        if (mb_strlen($titleAr) > 255) {
            return t('admin.tenders.title_required');
        }
        $entityNameEn = trim((string) $request->input('entity_name_en'));
        if ($entityNameEn === '' || mb_strlen($entityNameEn) > 150) {
            return t('admin.tenders.entity_required');
        }
        $entityNameAr = mb_substr(trim((string) $request->input('entity_name_ar', '')), 0, 150);

        $deadline = (string) $request->input('submission_deadline');
        if (! $this->isValidDate($deadline)) {
            return t('admin.tenders.deadline_invalid');
        }

        $value = $request->input('estimated_value_sar');
        $estimatedValue = ($value === null || $value === '') ? null : $this->validatedNumber($value, 0, 999999999999.99);
        if ($value !== null && $value !== '' && $estimatedValue === null) {
            return t('admin.tenders.est_value_invalid');
        }

        $sourceUrl = trim((string) $request->input('source_url', ''));
        if ($sourceUrl !== '' && (mb_strlen($sourceUrl) > 255 || filter_var($sourceUrl, FILTER_VALIDATE_URL) === false)) {
            return t('admin.tenders.source_url_invalid');
        }

        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'description_en' => (string) $request->input('description_en', ''),
            'description_ar' => (string) $request->input('description_ar', ''),
            'entity_name_en' => $entityNameEn,
            'entity_name_ar' => $entityNameAr,
            'category' => array_key_exists((string) $request->input('category'), Tender::TYPES) ? $request->input('category') : 'government',
            'estimated_value_sar' => $estimatedValue,
            'submission_deadline' => $deadline,
            'location_city' => mb_substr(trim((string) $request->input('location_city', '')), 0, 100),
            'source_url' => $sourceUrl ?: null,
            'sort_order' => (int) $sortOrder,
        ];
    }

    /** True only for a real calendar date in Y-m-d form — same shape as EstimateController::isValidOptionalDate(), but the deadline itself is required, never optional. */
    private function isValidDate(string $value): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));

        return checkdate($m, $d, $y);
    }

    /** Rejects a non-numeric or out-of-range value instead of silently casting it — see App\Http\Controllers\App\ProjectController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
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
