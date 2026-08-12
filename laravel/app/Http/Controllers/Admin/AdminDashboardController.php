<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'totalCompanies' => Company::count(),
            'activeCompanies' => Company::where('status', 'active')->count(),
        ]);
    }
}
