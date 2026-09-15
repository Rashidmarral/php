<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(string $slug): View
    {
        $page = Page::where('slug', $slug)->first();
        abort_if(!$page || !$page->is_published, 404, 'Page not found.');

        return view('site.page', [
            'pageTitle' => app()->getLocale() === 'ar' ? $page->title_ar : $page->title_en,
            'page' => $page,
        ]);
    }
}
