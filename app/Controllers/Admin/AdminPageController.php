<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Page;

class AdminPageController extends Controller
{
    public function index(): void
    {
        $this->view('admin/pages/index', [
            'pageTitle' => 'Website Pages',
            'pages' => Page::all('nav_order ASC, id DESC'),
        ], 'layouts/admin');
    }

    public function create(): void
    {
        $this->view('admin/pages/form', [
            'pageTitle' => 'New Page',
            'page' => null,
        ], 'layouts/admin');
    }

    public function store(): void
    {
        $this->verifyCsrf();

        $slug = $this->slugify((string) $this->input('slug'));
        if ($slug === '') {
            $this->flash('error', 'A URL slug is required.');
            self::redirect('/admin/pages/create');
        }
        if (Page::findBySlug($slug)) {
            $this->flash('error', 'A page with that URL slug already exists.');
            self::redirect('/admin/pages/create');
        }

        $id = Page::create($this->collectInput());
        $this->flash('success', 'Page created.');
        self::redirect('/admin/pages/' . $id . '/edit');
    }

    public function edit(string $id): void
    {
        $page = $this->findOrFail((int) $id);
        $this->view('admin/pages/form', [
            'pageTitle' => 'Edit Page',
            'page' => $page,
        ], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $page = $this->findOrFail((int) $id);

        $slug = $this->slugify((string) $this->input('slug'));
        if ($slug === '') {
            $this->flash('error', 'A URL slug is required.');
            self::redirect('/admin/pages/' . $page['id'] . '/edit');
        }
        $existing = Page::findBySlug($slug);
        if ($existing && (int) $existing['id'] !== $page['id']) {
            $this->flash('error', 'A page with that URL slug already exists.');
            self::redirect('/admin/pages/' . $page['id'] . '/edit');
        }

        Page::update($page['id'], $this->collectInput());
        $this->flash('success', 'Page updated.');
        self::redirect('/admin/pages/' . $page['id'] . '/edit');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $page = $this->findOrFail((int) $id);
        Page::delete($page['id']);
        $this->flash('success', 'Page deleted.');
        self::redirect('/admin/pages');
    }

    private function collectInput(): array
    {
        $slug = $this->slugify((string) $this->input('slug'));
        return [
            'slug' => $slug,
            'title_en' => trim((string) $this->input('title_en')),
            'title_ar' => trim((string) $this->input('title_ar')),
            'content_en' => (string) $this->input('content_en', ''),
            'content_ar' => (string) $this->input('content_ar', ''),
            'meta_description_en' => trim((string) $this->input('meta_description_en', '')),
            'meta_description_ar' => trim((string) $this->input('meta_description_ar', '')),
            'nav_label_en' => trim((string) $this->input('nav_label_en', '')),
            'nav_label_ar' => trim((string) $this->input('nav_label_ar', '')),
            'show_in_nav' => $this->input('show_in_nav') ? 1 : 0,
            'show_in_footer' => $this->input('show_in_footer') ? 1 : 0,
            'nav_order' => (int) $this->input('nav_order', 0),
            'is_published' => $this->input('is_published') ? 1 : 0,
        ];
    }

    private function slugify(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    private function findOrFail(int $id): array
    {
        $page = Page::find($id);
        if (!$page) {
            http_response_code(404);
            die('Page not found.');
        }
        return $page;
    }
}
