<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EstimateTemplate;
use App\Models\EstimateTemplateItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EstimateTemplateAdminController extends Controller
{
    public function index(): View
    {
        $templates = EstimateTemplate::query()
            ->select('estimate_templates.*')
            ->selectSub(
                DB::table('estimate_template_items')
                    ->whereColumn('template_id', 'estimate_templates.id')
                    ->selectRaw('COUNT(*)'),
                'item_count'
            )
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.estimate-templates.index', ['templates' => $templates]);
    }

    public function store(Request $request): RedirectResponse
    {
        $input = $this->collectTemplateInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/estimate-templates', 'error', $input);
        }
        EstimateTemplate::create($input + ['is_active' => true]);

        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.created'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $template = EstimateTemplate::findOrFail($id);
        $input = $this->collectTemplateInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/estimate-templates', 'error', $input);
        }
        $template->update($input + ['is_active' => (bool) $request->input('is_active')]);

        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.updated'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectTemplateInput(Request $request): array|string
    {
        $nameEn = trim((string) $request->input('name_en'));
        $nameAr = trim((string) $request->input('name_ar'));
        if ($nameEn === '' || $nameAr === '' || mb_strlen($nameEn) > 150 || mb_strlen($nameAr) > 150) {
            return t('admin.templates.name_required');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'name_en' => $nameEn,
            'name_ar' => $nameAr,
            'description_en' => mb_substr((string) $request->input('description_en', ''), 0, 255),
            'description_ar' => mb_substr((string) $request->input('description_ar', ''), 0, 255),
            'building_type' => mb_substr(trim((string) $request->input('building_type', '')), 0, 100),
            'icon' => mb_substr((string) $request->input('icon', ''), 0, 10) ?: '🏗️',
            'sort_order' => (int) $sortOrder,
        ];
    }

    public function destroy(int $id): RedirectResponse
    {
        EstimateTemplateItem::where('template_id', $id)->delete();
        EstimateTemplate::destroy($id);

        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.deleted'));
    }

    /** Only one template can be the default pre-selected choice on the "New Estimate" screen. */
    public function setDefault(int $id): RedirectResponse
    {
        EstimateTemplate::query()->update(['is_default_choice' => false]);
        EstimateTemplate::findOrFail($id)->update(['is_default_choice' => true]);

        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.default_updated'));
    }

    public function items(int $id): View
    {
        $template = EstimateTemplate::findOrFail($id);
        $items = EstimateTemplateItem::where('template_id', $template->id)->orderBy('sort_order')->get();

        return view('admin.estimate-templates.items', [
            'template' => $template,
            'items' => $items,
            'subtotal' => $items->sum(fn ($i) => (float) $i->default_qty * (float) $i->unit_cost),
        ]);
    }

    public function storeItem(Request $request, int $id): RedirectResponse
    {
        $template = EstimateTemplate::findOrFail($id);
        $input = $this->collectItemInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/estimate-templates/'.$template->id.'/items', 'error', $input);
        }
        EstimateTemplateItem::create($input + ['template_id' => $template->id]);

        return $this->redirectWithFlash('/admin/estimate-templates/'.$template->id.'/items', 'success', t('admin.estimate_templates.item_added'));
    }

    public function updateItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        $item = EstimateTemplateItem::find($itemId);
        if (! $item || $item->template_id !== $id) {
            abort(404, 'Line item not found.');
        }
        $input = $this->collectItemInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/estimate-templates/'.$id.'/items', 'error', $input);
        }
        $item->update($input);

        return $this->redirectWithFlash('/admin/estimate-templates/'.$id.'/items', 'success', t('admin.estimate_templates.item_updated'));
    }

    public function destroyItem(int $id, int $itemId): RedirectResponse
    {
        $item = EstimateTemplateItem::find($itemId);
        if ($item && $item->template_id === $id) {
            $item->delete();
            $this->flash('success', t('admin.estimate_templates.item_removed'));
        }

        return redirect('/admin/estimate-templates/'.$id.'/items');
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectItemInput(Request $request): array|string
    {
        $descriptionEn = trim((string) $request->input('description_en'));
        if ($descriptionEn === '' || mb_strlen($descriptionEn) > 255) {
            return t('admin.templates.description_required');
        }
        $descriptionAr = mb_substr(trim((string) $request->input('description_ar', '')), 0, 255);

        $defaultQty = $this->validatedNumberOrDefault($request->input('default_qty', 0), 0, 99999999.99, 0.0);
        if ($defaultQty === null) {
            return t('admin.templates.default_qty_invalid');
        }
        $unitCost = $this->validatedNumberOrDefault($request->input('unit_cost', 0), 0, 99999999.99, 0.0);
        if ($unitCost === null) {
            return t('admin.templates.unit_cost_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'section_number' => mb_substr((string) $request->input('section_number', '1.0') ?: '1.0', 0, 10),
            'section_title_en' => mb_substr(trim((string) $request->input('section_title_en')), 0, 150),
            'section_title_ar' => mb_substr(trim((string) $request->input('section_title_ar')), 0, 150),
            'item_number' => mb_substr((string) $request->input('item_number', '1.1') ?: '1.1', 0, 10),
            'description_en' => $descriptionEn,
            'description_ar' => $descriptionAr,
            'item_type' => in_array($request->input('item_type'), ['material', 'labor', 'equipment', 'subcontract'], true) ? $request->input('item_type') : 'material',
            'default_qty' => $defaultQty,
            'uom' => mb_substr((string) $request->input('uom', 'each') ?: 'each', 0, 20),
            'unit_cost' => $unitCost,
            'sort_order' => (int) $sortOrder,
        ];
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
