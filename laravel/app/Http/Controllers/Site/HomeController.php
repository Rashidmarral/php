<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Plan;
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
        return view('site.pricing', ['pageTitle' => 'Pricing', 'plans' => $plans]);
    }

    public function about(): View
    {
        return view('site.about', ['pageTitle' => 'About Us']);
    }

    public function contact(): View
    {
        return view('site.contact', ['pageTitle' => 'Contact Us']);
    }

    public function contactSubmit(): RedirectResponse
    {
        return $this->redirectWithFlash('/contact', 'success', 'Thanks for reaching out! Our Saudi sales team will contact you within one business day.');
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
