<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BankGuarantee;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\ScheduleTask;
use App\Models\Supplier;
use App\Models\VendorBill;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $projects = \Illuminate\Support\Facades\DB::table('projects as p')
            ->leftJoin('clients as c', 'c.id', '=', 'p.client_id')
            ->where('p.company_id', $companyId)
            ->orderByDesc('p.created_at')
            ->select('p.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return view('app.projects.index', [
            'projects' => $projects,
            'projectLimit' => Feature::projectLimit(),
            'withinProjectLimit' => Feature::withinProjectLimit(),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if (!Feature::withinProjectLimit()) {
            return $this->redirectWithFlash('/app/billing', 'error', "Your plan's project limit (" . Feature::projectLimit() . ') has been reached. Upgrade to create more.');
        }
        $clients = Client::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        return view('app.projects.form', ['clients' => $clients, 'project' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;

        if (!Feature::withinProjectLimit()) {
            return $this->redirectWithFlash('/app/billing', 'error', "Your plan's project limit has been reached. Upgrade to create more.");
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/projects/create', 'error', 'Project name is required.');
        }

        $project = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'description' => $request->input('description', ''),
            'description_ar' => $request->input('description_ar', ''),
            'status' => $request->input('status', 'planning'),
            'budget' => (float) $request->input('budget', 0),
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project created.');
        return redirect('/app/projects/' . $project->id);
    }

    public function show(int $id): View
    {
        $project = $this->findOwned($id);
        $client = $this->ownedClient($project->client_id, $project->company_id);
        $estimates = Estimate::where('project_id', $project->id)->get();
        $invoices = Invoice::where('project_id', $project->id)->get();
        $tasks = ScheduleTask::where('project_id', $project->id)->orderBy('start_date')->get();
        $changeOrders = ChangeOrder::where('project_id', $project->id)->orderByDesc('created_at')->get();
        $approvedTotal = $changeOrders->sum(fn ($co) => $co->status === 'approved' ? (float) $co->amount : 0);
        $photos = ProjectPhoto::where('project_id', $project->id)->orderByDesc('taken_on')->orderByDesc('created_at')->get();
        $vendorBills = VendorBill::where('project_id', $project->id)->orderByDesc('bill_date')->orderByDesc('id')->get();
        $suppliers = Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        $bankGuarantees = BankGuarantee::where('project_id', $project->id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get();
        $actualCostTotal = (float) $vendorBills->sum('amount');
        $revisedBudget = (float) $project->budget + $approvedTotal;

        return view('app.projects.show', [
            'project' => $project->toArray(),
            'client' => $client,
            'estimates' => $estimates->toArray(),
            'invoices' => $invoices->toArray(),
            'tasks' => $tasks->toArray(),
            'changeOrders' => $changeOrders->toArray(),
            'approvedChangeOrdersTotal' => $approvedTotal,
            'photos' => $photos->toArray(),
            'vendorBills' => $vendorBills->toArray(),
            'suppliers' => $suppliers->toArray(),
            'bankGuarantees' => $bankGuarantees->toArray(),
            'bankGuaranteeTypes' => BankGuarantee::TYPES,
            'actualCostTotal' => $actualCostTotal,
            'revisedBudget' => $revisedBudget,
            'budgetVariance' => $revisedBudget - $actualCostTotal,
            'budgetUsedPercent' => $revisedBudget > 0 ? min(999, round($actualCostTotal / $revisedBudget * 100)) : 0,
        ]);
    }

    public function edit(int $id): View
    {
        $project = $this->findOwned($id);
        $clients = Client::where('company_id', Auth::user()->company_id)->orderBy('name')->get();
        return view('app.projects.form', ['clients' => $clients, 'project' => $project->toArray()]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);

        $project->update([
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $project->company_id)?->id,
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'description' => $request->input('description', ''),
            'description_ar' => $request->input('description_ar', ''),
            'status' => $request->input('status', 'planning'),
            'budget' => (float) $request->input('budget', 0),
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
        ]);

        $this->flash('success', 'Project updated.');
        return redirect('/app/projects/' . $project->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);
        $project->delete();
        return $this->redirectWithFlash('/app/projects', 'success', 'Project deleted.');
    }

    private function findOwned(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }

    /** Only returns the client if it belongs to $companyId — never leak another company's contact data via a foreign key. */
    private function ownedClient(?int $id, int $companyId): ?Client
    {
        if (!$id) {
            return null;
        }
        $client = Client::find($id);
        return ($client && $client->company_id === $companyId) ? $client : null;
    }
}
