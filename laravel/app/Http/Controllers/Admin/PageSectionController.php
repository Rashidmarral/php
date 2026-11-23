<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
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
            'customPageSlugs' => Page::orderBy('slug')->pluck('slug'),
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
            'customPageSlugs' => Page::orderBy('slug')->pluck('slug'),
            'types' => PageSection::TYPES,
            'maxItems' => self::MAX_ITEMS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new PageSection);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        return $this->save($request, PageSection::findOrFail($id));
    }

    private function save(Request $request, PageSection $section): RedirectResponse
    {
        $pageSlug = strtolower(trim((string) $request->input('page_slug')));
        $type = $request->input('section_type');
        if ($pageSlug === '' || ! array_key_exists($type, PageSection::TYPES)) {
            return $this->redirectWithFlash('/admin/sections', 'error', t('admin.sections.page_type_required'));
        }
        if (mb_strlen($pageSlug) > 40 || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $pageSlug) !== 1) {
            return $this->redirectWithFlash('/admin/sections', 'error', t('admin.sections.page_invalid'));
        }
        // The section only ever renders if $pageSlug matches one of the fixed marketing pages
        // (partials.custom-sections is included with that literal slug) or an existing custom
        // Page record (site/page.blade.php includes it with $page->slug) — anything else would
        // silently create an orphaned row that never appears anywhere, so it's rejected here
        // rather than trusted as free text.
        if (! array_key_exists($pageSlug, ContentController::PAGES) && ! Page::where('slug', $pageSlug)->exists()) {
            return $this->redirectWithFlash('/admin/sections', 'error', t('admin.sections.page_invalid'));
        }

        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return $this->redirectWithFlash('/admin/sections', 'error', t('common.sort_order_invalid'));
        }

        $items = [];
        foreach (array_slice((array) $request->input('items', []), 0, self::MAX_ITEMS) as $row) {
            $row = array_map(fn ($v) => mb_substr(trim((string) $v), 0, 255), (array) $row);
            if (array_filter($row) === []) {
                continue;
            }
            $items[] = $row;
        }

        $section->fill([
            'page_slug' => $pageSlug,
            'section_type' => $type,
            'title_en' => mb_substr(trim((string) $request->input('title_en', '')), 0, 200) ?: null,
            'title_ar' => mb_substr(trim((string) $request->input('title_ar', '')), 0, 200) ?: null,
            'subtitle_en' => mb_substr(trim((string) $request->input('subtitle_en', '')), 0, 500) ?: null,
            'subtitle_ar' => mb_substr(trim((string) $request->input('subtitle_ar', '')), 0, 500) ?: null,
            'body_en' => trim((string) $request->input('body_en', '')) ?: null,
            'body_ar' => trim((string) $request->input('body_ar', '')) ?: null,
            'button_text_en' => mb_substr(trim((string) $request->input('button_text_en', '')), 0, 100) ?: null,
            'button_text_ar' => mb_substr(trim((string) $request->input('button_text_ar', '')), 0, 100) ?: null,
            'button_url' => mb_substr(trim((string) $request->input('button_url', '')), 0, 255) ?: null,
            'items' => $items,
            'sort_order' => (int) $sortOrder,
            'is_active' => $request->has('is_active'),
        ]);
        $section->save();

        $this->flash('success', t('admin.sections.saved'));

        return redirect('/admin/sections');
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

    public function destroy(int $id): RedirectResponse
    {
        PageSection::findOrFail($id)->delete();
        $this->flash('success', t('admin.sections.removed'));

        return redirect('/admin/sections');
    }
}
