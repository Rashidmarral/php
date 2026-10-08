<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entry gate for the whole /app (company) panel. Platform admins are bounced to /admin.
 * A company whose trial has ended, whose auto-renewal has failed repeatedly (past_due), or
 * that an admin has suspended/cancelled is redirected to Billing on every page except
 * Billing/logout, since nothing else in the app checks subscription/account state.
 */
class EnsureCompanyUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();

        if ($user->isAdminStaff()) {
            return redirect('/admin');
        }

        if ($blocked = $this->blockIfSubscriptionIssue($request, $user)) {
            return $blocked;
        }

        return $next($request);
    }

    private function blockIfSubscriptionIssue(Request $request, $user): ?Response
    {
        if (!$user->company_id || $this->onExemptPath($request)) {
            return null;
        }

        $company = Company::find($user->company_id);
        if (!$company) {
            return null;
        }

        if ($company->status === 'trial' && $company->trial_ends_at && $company->trial_ends_at->lt(now()->startOfDay())) {
            $request->session()->flash('flash.error', ['Your trial has ended. Choose a plan to continue using ' . Setting::siteName() . '.']);
            return redirect('/app/billing');
        }

        if ($company->status === 'past_due') {
            $request->session()->flash('flash.error', ["We couldn't renew your subscription. Please update your payment method to continue."]);
            return redirect('/app/billing');
        }

        if (in_array($company->status, ['suspended', 'cancelled'], true)) {
            $request->session()->flash('flash.error', ['Your account has been ' . $company->status . '. Contact support if you believe this is a mistake.']);
            return redirect('/app/billing');
        }

        return null;
    }

    private function onExemptPath(Request $request): bool
    {
        return $request->is('app/billing*') || $request->is('logout') || $request->is('app/end-impersonation');
    }
}
