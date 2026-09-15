<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaterialLibraryItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaterialLibraryAdminController extends Controller
{
    /** Fixed, consistent category list used both here and on the company-facing browse page. */
    public const CATEGORIES = [
        'Concrete & Steel', 'Masonry', 'Finishing & Tiling', 'Plumbing', 'Electrical',
        'HVAC', 'Paint & Coatings', 'Doors & Windows', 'Waterproofing & Insulation',
        'Aggregates', 'Sanitaryware', 'Glass & Aluminum',
    ];

    public function index(): View
    {
        $items = MaterialLibraryItem::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('admin.material-library.index', [
            'items' => $items,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/admin/material-library', 'error', 'Item name is required.');
        }
        MaterialLibraryItem::create([...$this->fromInput($request), 'is_active' => true]);
        return $this->redirectWithFlash('/admin/material-library', 'success', 'Library item added.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        MaterialLibraryItem::findOrFail($id)->update([
            ...$this->fromInput($request),
            'is_active' => (bool) $request->input('is_active'),
        ]);
        return $this->redirectWithFlash('/admin/material-library', 'success', 'Library item updated.');
    }

    public function destroy(int $id): RedirectResponse
    {
        MaterialLibraryItem::destroy($id);
        return $this->redirectWithFlash('/admin/material-library', 'success', 'Library item deleted.');
    }

    private function fromInput(Request $request): array
    {
        return [
            'sku' => trim((string) $request->input('sku', '')) ?: null,
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')) ?: null,
            'category' => $request->input('category', '') ?: 'Other',
            'unit' => $request->input('unit', 'each') ?: 'each',
            'material_cost' => (float) $request->input('material_cost', 0),
            'labor_cost' => (float) $request->input('labor_cost', 0),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
