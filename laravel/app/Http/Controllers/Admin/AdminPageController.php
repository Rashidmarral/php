<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminPageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('nav_order')->orderByDesc('id')->get()]);
    }

    public function create(): View
    {
        return view('admin.pages.form', ['page' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $slug = $this->slugify((string) $request->input('slug'));
        if ($slug === '' || mb_strlen($slug) > 150) {
            return $this->redirectWithFlash('/admin/pages/create', 'error', t('admin.pages.slug_required'));
        }
        if (Page::where('slug', $slug)->exists()) {
            return $this->redirectWithFlash('/admin/pages/create', 'error', t('admin.pages.slug_exists'));
        }

        $input = $this->collectInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/pages/create', 'error', $input);
        }

        $page = Page::create($input);
        $this->flash('success', t('admin.pages.created'));

        return redirect('/admin/pages/'.$page->id.'/edit');
    }

    public function edit(int $id): View
    {
        return view('admin.pages.form', ['page' => Page::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = Page::findOrFail($id);

        $slug = $this->slugify((string) $request->input('slug'));
        if ($slug === '' || mb_strlen($slug) > 150) {
            return $this->redirectWithFlash('/admin/pages/'.$page->id.'/edit', 'error', t('admin.pages.slug_required'));
        }
        $existing = Page::where('slug', $slug)->first();
        if ($existing && $existing->id !== $page->id) {
            return $this->redirectWithFlash('/admin/pages/'.$page->id.'/edit', 'error', t('admin.pages.slug_exists'));
        }

        $input = $this->collectInput($request);
        if (is_string($input)) {
            return $this->redirectWithFlash('/admin/pages/'.$page->id.'/edit', 'error', $input);
        }

        $page->update($input);
        $this->flash('success', t('admin.pages.updated'));

        return redirect('/admin/pages/'.$page->id.'/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        Page::findOrFail($id)->delete();

        return $this->redirectWithFlash('/admin/pages', 'success', t('admin.pages.deleted'));
    }

    /** Returns the validated/bounded column data, or a translated error message string when a required field is missing or a value is out of range. */
    private function collectInput(Request $request): array|string
    {
        $titleEn = trim((string) $request->input('title_en'));
        $titleAr = trim((string) $request->input('title_ar'));
        if ($titleEn === '' || $titleAr === '') {
            return t('admin.pages.title_required');
        }
        if (mb_strlen($titleEn) > 200 || mb_strlen($titleAr) > 200) {
            return t('admin.pages.title_required');
        }

        $navOrder = $this->validatedNumberOrDefault($request->input('nav_order', 0), 0, 99999, 0.0);
        if ($navOrder === null) {
            return t('common.sort_order_invalid');
        }

        return [
            'slug' => $this->slugify((string) $request->input('slug')),
            'title_en' => $titleEn,
            'title_ar' => $titleAr,
            'content_en' => (string) $request->input('content_en', ''),
            'content_ar' => (string) $request->input('content_ar', ''),
            'meta_description_en' => mb_substr(trim((string) $request->input('meta_description_en', '')), 0, 255),
            'meta_description_ar' => mb_substr(trim((string) $request->input('meta_description_ar', '')), 0, 255),
            'nav_label_en' => mb_substr(trim((string) $request->input('nav_label_en', '')), 0, 80),
            'nav_label_ar' => mb_substr(trim((string) $request->input('nav_label_ar', '')), 0, 80),
            'show_in_nav' => (bool) $request->input('show_in_nav'),
            'show_in_footer' => (bool) $request->input('show_in_footer'),
            'nav_order' => (int) $navOrder,
            'is_published' => (bool) $request->input('is_published'),
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

    private function slugify(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

        return trim($slug, '-');
    }
}
