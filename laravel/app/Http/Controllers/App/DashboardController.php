<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Company;
use App\Models\ComplianceDocument;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\ScheduleTask;
use App\Support\Feature;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);

        $trialDaysLeft = null;
        if ($company && $company->status === 'trial' && $company->trial_ends_at) {
            $trialDaysLeft = (int) ceil((strtotime($company->trial_ends_at->format('Y-m-d')) - strtotime(now()->format('Y-m-d'))) / 86400);
        }
        $currentPlan = $company && $company->plan_id ? Plan::find($company->plan_id) : null;
        $expiringDocs = ComplianceDocument::expiringWithin($companyId, 30)->toArray();

        $activeProjects = Project::where('company_id', $companyId)->where('status', '!=', 'completed')->count();
        $totalBudget = Project::where('company_id', $companyId)->sum('budget');
        $outstanding = Invoice::where('company_id', $companyId)->where('status', '!=', 'paid')->sum('total');

        $thisMonth = now()->format('Y-m');
        $paidThisMonth = Invoice::where('company_id', $companyId)->where('status', 'paid')->get()
            ->sum(fn ($row) => substr((string) $row->created_at, 0, 7) === $thisMonth ? (float) $row->total : 0);

        // Cheap, plan-gated KPI tile: a quick visibility count, not a full report — keeps
        // the existing kpi-grid pattern, no restructuring. Null (rather than 0) when the
        // plan lacks the feature at all, so the tile hides instead of showing a false "0".
        $openSafetyIncidents = Feature::allows('safety_tracking')
            ? SafetyIncident::where('company_id', $companyId)->whereIn('status', ['open', 'under_investigation'])->count()
            : null;

        $recentProjects = Project::where('company_id', $companyId)->orderByDesc('created_at')->limit(5)->get()->toArray();
        $upcomingTasks = ScheduleTask::where('company_id', $companyId)->where('status', '!=', 'done')->orderBy('start_date')->limit(6)->get()->toArray();
        $recentEstimates = Estimate::where('company_id', $companyId)->orderByDesc('created_at')->limit(5)->get()->toArray();

        return view('app.dashboard', [
            'activeProjects' => $activeProjects,
            'totalBudget' => $totalBudget,
            'outstanding' => $outstanding,
            'paidThisMonth' => $paidThisMonth,
            'recentProjects' => $recentProjects,
            'upcomingTasks' => $upcomingTasks,
            'recentEstimates' => $recentEstimates,
            'clientCount' => Client::where('company_id', $companyId)->count(),
            'trialDaysLeft' => $trialDaysLeft,
            'currentPlan' => $currentPlan,
            'expiringDocs' => $expiringDocs,
            'openSafetyIncidents' => $openSafetyIncidents,
        ]);
    }
}
