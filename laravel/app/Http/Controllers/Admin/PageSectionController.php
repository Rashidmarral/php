<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lets the admin add brand-new sections to any public page (on top of the prebuilt ones editable
 * via Page Content) — a feature grid, a text block, a CTA banner, or a stat row — built from plain
 * fields, never raw HTML. Rendered through the same modern components (card/feature-card/stat-row/
 * section) the rest of the site already uses, via partials.custom-sections.
 */
class PageSectionController extends Controller
{
    private const MAX_ITEMS = 6;

    public function index(): View
    {
        $sections = PageSection::orderBy('page_slug')->orderBy('sort_order')->get()->groupBy('page_slug');
        return view('admin.sections.index', [
            'sections' => $sections,
            'pages' => ContentController::PAGES,
            'types' => PageSection::TYPES,
        ]);
    }

    public function create(): View
    {
        return view('admin.sections.form', [
            'section' => null,
            'pages' => ContentController::PAGES,
            'types' => PageSection::TYPES,
            'maxItems' => self::MAX_ITEMS,
        ]);
    }

    public function edit(int $id): View
    {
        $section = PageSection::findOrFail($id);
        return view('admin.sections.form', [
            'section' => $section,
            'pages' => ContentController::PAGES,
            'types' => PageSection::TYPES,
            'maxItems' => self::MAX_ITEMS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new PageSection());
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        return $this->save($request, PageSection::findOrFail($id));
    }

    private function save(Request $request, PageSection $section): RedirectResponse
    {
        $pageSlug = trim((string) $request->input('page_slug'));
        $type = $request->input('section_type');
        if ($pageSlug === '' || !array_key_exists($type, PageSection::TYPES)) {
            return $this->redirectWithFlash('/admin/sections', 'error', t('admin.sections.page_type_required'));
        }

        $items = [];
        foreach ((array) $request->input('items', []) as $row) {
            $row = array_map(fn ($v) => trim((string) $v), (array) $row);
            if (array_filter($row) === []) {
                continue;
            }
            $items[] = $row;
        }

        $section->fill([
            'page_slug' => $pageSlug,
            'section_type' => $type,
            'title_en' => trim((string) $request->input('title_en', '')) ?: null,
            'title_ar' => trim((string) $request->input('title_ar', '')) ?: null,
            'subtitle_en' => trim((string) $request->input('subtitle_en', '')) ?: null,
            'subtitle_ar' => trim((string) $request->input('subtitle_ar', '')) ?: null,
            'body_en' => trim((string) $request->input('body_en', '')) ?: null,
            'body_ar' => trim((string) $request->input('body_ar', '')) ?: null,
            'button_text_en' => trim((string) $request->input('button_text_en', '')) ?: null,
            'button_text_ar' => trim((string) $request->input('button_text_ar', '')) ?: null,
            'button_url' => trim((string) $request->input('button_url', '')) ?: null,
            'items' => $items,
            'sort_order' => (int) $request->input('sort_order', 0),
            'is_active' => $request->has('is_active'),
        ]);
        $section->save();

        $this->flash('success', t('admin.sections.saved'));
        return redirect('/admin/sections');
    }

    public function destroy(int $id): RedirectResponse
    {
        PageSection::findOrFail($id)->delete();
        $this->flash('success', t('admin.sections.removed'));
        return redirect('/admin/sections');
    }
}
