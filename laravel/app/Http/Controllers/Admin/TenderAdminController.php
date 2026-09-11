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
    public function index(): View
    {
        return view('admin.tenders.index', [
            'tenders' => Tender::orderBy('submission_deadline')->orderBy('sort_order')->get(),
            'categories' => Tender::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.tenders.form', ['tender' => null, 'categories' => Tender::TYPES]);
    }

    public function store(Request $request): RedirectResponse
    {
        $titleEn = trim((string) $request->input('title_en'));
        if ($titleEn === '') {
            return $this->redirectWithFlash('/admin/tenders/create', 'error', 'Please enter a tender title.');
        }

        $tender = Tender::create($this->collectInput($request) + ['is_active' => true]);
        $this->flash('success', 'Tender added.');
        return redirect('/admin/tenders/' . $tender->id . '/edit');
    }

    public function edit(int $id): View
    {
        return view('admin.tenders.form', ['tender' => Tender::findOrFail($id)->toArray(), 'categories' => Tender::TYPES]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $tender = Tender::findOrFail($id);

        $titleEn = trim((string) $request->input('title_en'));
        if ($titleEn === '') {
            return $this->redirectWithFlash('/admin/tenders/' . $tender->id . '/edit', 'error', 'Please enter a tender title.');
        }

        $tender->update($this->collectInput($request) + [
            'is_active' => (bool) $request->input('is_active'),
        ]);
        $this->flash('success', 'Tender updated.');
        return redirect('/admin/tenders/' . $tender->id . '/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        Tender::findOrFail($id)->delete();
        return $this->redirectWithFlash('/admin/tenders', 'success', 'Tender deleted.');
    }

    private function collectInput(Request $request): array
    {
        $value = $request->input('estimated_value_sar');

        return [
            'title_en' => trim((string) $request->input('title_en')),
            'title_ar' => trim((string) $request->input('title_ar', '')),
            'description_en' => (string) $request->input('description_en', ''),
            'description_ar' => (string) $request->input('description_ar', ''),
            'entity_name_en' => trim((string) $request->input('entity_name_en')),
            'entity_name_ar' => trim((string) $request->input('entity_name_ar', '')),
            'category' => array_key_exists((string) $request->input('category'), Tender::TYPES) ? $request->input('category') : 'government',
            'estimated_value_sar' => ($value === null || $value === '') ? null : (float) $value,
            'submission_deadline' => $request->input('submission_deadline'),
            'location_city' => trim((string) $request->input('location_city', '')),
            'source_url' => trim((string) $request->input('source_url', '')) ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }
}
