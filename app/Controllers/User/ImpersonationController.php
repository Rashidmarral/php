<?php

namespace App\Controllers\User;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;
use App\Models\User;

class ImpersonationController extends Controller
{
    public function stop(): void
    {
        $this->verifyCsrf();
        $adminId = $_SESSION['impersonator_admin_id'] ?? null;
        if (!$adminId) {
            self::redirect('/app');
        }
        $admin = User::find((int) $adminId);
        $companyId = Auth::companyId();
        $companyName = $companyId ? (Company::find($companyId)['name'] ?? '') : '';
        unset($_SESSION['impersonator_admin_id']);
        if (!$admin) {
            self::redirect('/login');
        }
        Audit::log('impersonate_end', 'company', $companyId, "{$admin['name']} returned from viewing as {$companyName}");
        Auth::login($admin);
        self::redirect('/admin/companies' . ($companyId ? '/' . $companyId : ''));
    }
}
