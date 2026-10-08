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
        EstimateTemplate::create([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'building_type' => trim((string) $request->input('building_type', '')),
            'icon' => $request->input('icon', '') ?: '🏗️',
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => true,
        ]);
        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.created'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        EstimateTemplate::findOrFail($id)->update([
            'name_en' => trim((string) $request->input('name_en')),
            'name_ar' => trim((string) $request->input('name_ar')),
            'description_en' => $request->input('description_en', ''),
            'description_ar' => $request->input('description_ar', ''),
            'building_type' => trim((string) $request->input('building_type', '')),
            'icon' => $request->input('icon', '') ?: '🏗️',
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/estimate-templates', 'success', t('admin.estimate_templates.updated'));
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
        EstimateTemplateItem::create([
            'template_id' => $template->id,
            'section_number' => $request->input('section_number', '1.0') ?: '1.0',
            'section_title_en' => trim((string) $request->input('section_title_en')),
            'section_title_ar' => trim((string) $request->input('section_title_ar')),
            'item_number' => $request->input('item_number', '1.1') ?: '1.1',
            'description_en' => trim((string) $request->input('description_en')),
            'description_ar' => trim((string) $request->input('description_ar')),
            'item_type' => in_array($request->input('item_type'), ['material', 'labor', 'equipment', 'subcontract'], true) ? $request->input('item_type') : 'material',
            'default_qty' => (float) $request->input('default_qty', 0),
            'uom' => $request->input('uom', 'each') ?: 'each',
            'unit_cost' => (float) $request->input('unit_cost', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        return $this->redirectWithFlash('/admin/estimate-templates/' . $template->id . '/items', 'success', t('admin.estimate_templates.item_added'));
    }

    public function updateItem(Request $request, int $id, int $itemId): RedirectResponse
    {
        $item = EstimateTemplateItem::find($itemId);
        if (!$item || $item->template_id !== $id) {
            abort(404, 'Line item not found.');
        }
        $item->update([
            'section_number' => $request->input('section_number', '1.0') ?: '1.0',
            'section_title_en' => trim((string) $request->input('section_title_en')),
            'section_title_ar' => trim((string) $request->input('section_title_ar')),
            'item_number' => $request->input('item_number', '1.1') ?: '1.1',
            'description_en' => trim((string) $request->input('description_en')),
            'description_ar' => trim((string) $request->input('description_ar')),
            'item_type' => in_array($request->input('item_type'), ['material', 'labor', 'equipment', 'subcontract'], true) ? $request->input('item_type') : 'material',
            'default_qty' => (float) $request->input('default_qty', 0),
            'uom' => $request->input('uom', 'each') ?: 'each',
            'unit_cost' => (float) $request->input('unit_cost', 0),
            'sort_order' => (int) $request->input('sort_order', 0),
        ]);
        return $this->redirectWithFlash('/admin/estimate-templates/' . $id . '/items', 'success', t('admin.estimate_templates.item_updated'));
    }

    public function destroyItem(int $id, int $itemId): RedirectResponse
    {
        $item = EstimateTemplateItem::find($itemId);
        if ($item && $item->template_id === $id) {
            $item->delete();
            $this->flash('success', t('admin.estimate_templates.item_removed'));
        }
        return redirect('/admin/estimate-templates/' . $id . '/items');
    }
}
