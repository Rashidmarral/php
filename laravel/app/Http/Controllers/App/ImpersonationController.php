<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function stop(): RedirectResponse
    {
        $adminId = session('impersonator_admin_id');
        if (!$adminId) {
            return redirect('/app');
        }
        $admin = User::find($adminId);
        $companyId = Auth::user()->company_id;
        $companyName = $companyId ? (Company::find($companyId)->name ?? '') : '';
        session()->forget('impersonator_admin_id');
        if (!$admin) {
            return redirect('/login');
        }
        AuditLog::record($admin, 'impersonate_end', 'company', $companyId, "{$admin->name} returned from viewing as {$companyName}");
        Auth::login($admin);
        return redirect('/admin/companies' . ($companyId ? '/' . $companyId : ''));
    }
}
