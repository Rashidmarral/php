<?php

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Lang;
use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug): void
    {
        $page = Page::findBySlug($slug);
        if (!$page || !$page['is_published']) {
            http_response_code(404);
            $this->view('errors/404', []);
            return;
        }

        $locale = Lang::locale();
        $this->view('site/page', [
            'pageTitle' => $locale === 'ar' ? $page['title_ar'] : $page['title_en'],
            'page' => $page,
        ]);
    }
}
