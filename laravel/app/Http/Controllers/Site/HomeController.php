<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Plan;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('site.home', ['pageTitle' => 'Construction Management Software for Saudi Contractors']);
    }

    public function features(): View
    {
        return view('site.features', ['pageTitle' => 'Features']);
    }

    public function pricing(): View
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        return view('site.pricing', ['pageTitle' => 'Pricing', 'plans' => $plans, 'allFeatures' => Feature::ALL]);
    }

    public function about(): View
    {
        return view('site.about', [
            'pageTitle' => 'About Us',
            'certificates' => Certificate::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function support(): View
    {
        return view('site.support', ['pageTitle' => 'Help Center']);
    }

    public function security(): View
    {
        return view('site.security', ['pageTitle' => 'Security & Compliance']);
    }

    public function contact(): View
    {
        return view('site.contact', ['pageTitle' => 'Contact Us']);
    }

    public function contactSubmit(): RedirectResponse
    {
        return $this->redirectWithFlash('/contact', 'success', t('site.contact.submitted'));
    }

    public function privacy(): View
    {
        return view('site.legal', ['pageTitle' => 'Privacy Policy', 'heading' => 'Privacy Policy', 'type' => 'privacy']);
    }

    public function terms(): View
    {
        return view('site.legal', ['pageTitle' => 'Terms of Service', 'heading' => 'Terms of Service', 'type' => 'terms']);
    }
}
