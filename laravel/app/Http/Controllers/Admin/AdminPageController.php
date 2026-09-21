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
        if ($slug === '') {
            return $this->redirectWithFlash('/admin/pages/create', 'error', t('admin.pages.slug_required'));
        }
        if (Page::where('slug', $slug)->exists()) {
            return $this->redirectWithFlash('/admin/pages/create', 'error', t('admin.pages.slug_exists'));
        }

        $page = Page::create($this->collectInput($request));
        $this->flash('success', t('admin.pages.created'));
        return redirect('/admin/pages/' . $page->id . '/edit');
    }

    public function edit(int $id): View
    {
        return view('admin.pages.form', ['page' => Page::findOrFail($id)]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $page = Page::findOrFail($id);

        $slug = $this->slugify((string) $request->input('slug'));
        if ($slug === '') {
            return $this->redirectWithFlash('/admin/pages/' . $page->id . '/edit', 'error', t('admin.pages.slug_required'));
        }
        $existing = Page::where('slug', $slug)->first();
        if ($existing && $existing->id !== $page->id) {
            return $this->redirectWithFlash('/admin/pages/' . $page->id . '/edit', 'error', t('admin.pages.slug_exists'));
        }

        $page->update($this->collectInput($request));
        $this->flash('success', t('admin.pages.updated'));
        return redirect('/admin/pages/' . $page->id . '/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        Page::findOrFail($id)->delete();
        return $this->redirectWithFlash('/admin/pages', 'success', t('admin.pages.deleted'));
    }

    private function collectInput(Request $request): array
    {
        return [
            'slug' => $this->slugify((string) $request->input('slug')),
            'title_en' => trim((string) $request->input('title_en')),
            'title_ar' => trim((string) $request->input('title_ar')),
            'content_en' => (string) $request->input('content_en', ''),
            'content_ar' => (string) $request->input('content_ar', ''),
            'meta_description_en' => trim((string) $request->input('meta_description_en', '')),
            'meta_description_ar' => trim((string) $request->input('meta_description_ar', '')),
            'nav_label_en' => trim((string) $request->input('nav_label_en', '')),
            'nav_label_ar' => trim((string) $request->input('nav_label_ar', '')),
            'show_in_nav' => (bool) $request->input('show_in_nav'),
            'show_in_footer' => (bool) $request->input('show_in_footer'),
            'nav_order' => (int) $request->input('nav_order', 0),
            'is_published' => (bool) $request->input('is_published'),
        ];
    }

    private function slugify(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }
}
