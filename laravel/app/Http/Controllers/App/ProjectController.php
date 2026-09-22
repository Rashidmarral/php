<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BankGuarantee;
use App\Models\BoqItem;
use App\Models\ChangeOrder;
use App\Models\Client;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\ExtensionOfTimeRequest;
use App\Models\Invoice;
use App\Models\PaymentCertificate;
use App\Models\Project;
use App\Models\ProjectPhoto;
use App\Models\PunchListItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ScheduleTask;
use App\Models\SiteLog;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VendorBill;
use App\Support\Feature;
use App\Support\WhatsApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProjectController extends Controller
{
    private const STATUSES = ['planning', 'in_progress', 'on_hold', 'completed'];

    public function index(Request $request): View
    {
        $companyId = Auth::user()->company_id;
        $status = (string) $request->input('status', '');
        $q = trim((string) $request->input('q', ''));

        $query = DB::table('projects as p')
            ->leftJoin('clients as c', 'c.id', '=', 'p.client_id')
            ->where('p.company_id', $companyId);

        if (in_array($status, self::STATUSES, true)) {
            $query->where('p.status', $status);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('p.name', 'like', "%{$q}%")
                    ->orWhere('p.name_ar', 'like', "%{$q}%")
                    ->orWhere('c.name', 'like', "%{$q}%")
                    ->orWhere('c.name_ar', 'like', "%{$q}%");
            });
        }

        $budgetHealth = $this->budgetHealthByProject($companyId);
        $today = now()->format('Y-m-d');

        $projects = $query->orderByDesc('p.created_at')
            ->select('p.*', 'c.name as client_name', 'c.name_ar as client_name_ar')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->map(function ($row) use ($budgetHealth, $today) {
                $health = $budgetHealth[$row['id']] ?? ['availableBudget' => (float) $row['budget']];
                $row['isOverBudget'] = $health['availableBudget'] < 0;
                $row['isBehindSchedule'] = !empty($row['end_date']) && $row['end_date'] < $today && $row['status'] !== 'completed';
                return $row;
            })
            ->all();

        return view('app.projects.index', [
            'projects' => $projects,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'q' => $q,
            'counts' => $this->statusCounts($companyId),
            'stats' => $this->portfolioStats($companyId, $budgetHealth),
            'projectLimit' => Feature::projectLimit(),
            'withinProjectLimit' => Feature::withinProjectLimit(),
        ]);
    }

    /** Per-status counts across the whole company (not narrowed by the current filter/search) for the filter toolbar — same pattern as EstimateController::statusCounts(). */
    private function statusCounts(int $companyId): array
    {
        $rows = DB::table('projects')->where('company_id', $companyId)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->get();
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = (int) $row->c;
            }
        }
        return $counts;
    }

    /**
     * Per-project budget health, computed with the exact same formula show() uses for a single
     * project — revisedBudget = budget + approved change orders; availableBudget = revisedBudget
     * - actual vendor bills - committed (issued) purchase orders. One grouped-sum query per
     * related table keeps this at a fixed query count regardless of project count (no N+1); the
     * small amount of arithmetic is duplicated here rather than shared with show(), matching this
     * app's existing precedent of duplicating a formula this small rather than inventing a
     * divergent shortcut (see show()'s own inline comment on committedTotal above).
     *
     * @return array<int, array{revisedBudget: float, availableBudget: float}>
     */
    private function budgetHealthByProject(int $companyId): array
    {
        $actualByProject = VendorBill::where('company_id', $companyId)
            ->select('project_id', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');
        $committedByProject = PurchaseOrder::where('company_id', $companyId)
            ->where('status', 'issued')
            ->select('project_id', DB::raw('SUM(total) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');
        $approvedCoByProject = ChangeOrder::where('company_id', $companyId)
            ->where('status', 'approved')
            ->select('project_id', DB::raw('SUM(amount) as total'))
            ->groupBy('project_id')
            ->pluck('total', 'project_id');

        $health = [];
        foreach (Project::where('company_id', $companyId)->get(['id', 'budget']) as $project) {
            $revisedBudget = (float) $project->budget + (float) ($approvedCoByProject[$project->id] ?? 0);
            $availableBudget = $revisedBudget - (float) ($actualByProject[$project->id] ?? 0) - (float) ($committedByProject[$project->id] ?? 0);
            $health[$project->id] = [
                'revisedBudget' => $revisedBudget,
                'availableBudget' => $availableBudget,
            ];
        }
        return $health;
    }

    /**
     * Portfolio-level KPIs shown above the project list: active project count, total revised
     * budget under management across active projects, and counts of projects currently over
     * budget / behind schedule — computed across the whole company regardless of the current
     * filter/search, same as EstimateController::pipelineStats().
     */
    private function portfolioStats(int $companyId, array $budgetHealth): array
    {
        $activeStatuses = ['planning', 'in_progress', 'on_hold'];
        $projects = Project::where('company_id', $companyId)->get(['id', 'status', 'end_date']);
        $today = now()->format('Y-m-d');

        $activeCount = 0;
        $activeBudget = 0.0;
        $overBudgetCount = 0;
        $behindScheduleCount = 0;
        foreach ($projects as $project) {
            $isActive = in_array($project->status, $activeStatuses, true);
            $health = $budgetHealth[$project->id] ?? ['revisedBudget' => 0.0, 'availableBudget' => 0.0];
            if ($isActive) {
                $activeCount++;
                $activeBudget += $health['revisedBudget'];
            }
            if ($health['availableBudget'] < 0) {
                $overBudgetCount++;
            }
            if ($project->end_date && $project->end_date->format('Y-m-d') < $today && $project->status !== 'completed') {
                $behindScheduleCount++;
            }
        }

        return [
            'activeCount' => $activeCount,
            'activeBudget' => $activeBudget,
            'overBudgetCount' => $overBudgetCount,
            'behindScheduleCount' => $behindScheduleCount,
        ];
    }

    public function create(): View|RedirectResponse
    {
        if (!Feature::withinProjectLimit()) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.projects.limit_reached_with_count', ['limit' => Feature::projectLimit()]));
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
            return $this->redirectWithFlash('/app/billing', 'error', t('user.projects.limit_reached'));
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/projects/create', 'error', t('user.projects.name_required'));
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
            'advance_payment_amount' => (float) $request->input('advance_payment_amount', 0),
            'advance_recovery_percent' => $request->filled('advance_recovery_percent') ? min(100, max(0, (float) $request->input('advance_recovery_percent'))) : null,
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
            'defects_liability_end_date' => $request->input('defects_liability_end_date') ?: null,
            'actual_completion_date' => $request->input('actual_completion_date') ?: null,
            'ld_rate_per_day' => $request->filled('ld_rate_per_day') ? (float) $request->input('ld_rate_per_day') : null,
            'ld_cap_percent' => $request->filled('ld_cap_percent') ? min(100, max(0, (float) $request->input('ld_cap_percent'))) : null,
        ]);

        $this->flash('success', t('user.projects.created'));
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
        $purchaseOrders = PurchaseOrder::where('project_id', $project->id)->orderByDesc('created_at')->get();
        $company = Company::find($project->company_id);
        // A draft PO isn't a real commitment to the supplier yet — see
        // PurchaseOrderController::sendWhatsApp()'s own docblock for why only
        // 'issued'/'received' POs get a wa.me link here.
        $poWhatsappLinks = [];
        foreach ($purchaseOrders as $po) {
            if (in_array($po->status, ['issued', 'received'], true)) {
                $poSupplier = $suppliers->firstWhere('id', $po->supplier_id);
                if ($poSupplier && !empty($poSupplier->phone)) {
                    $poWhatsappLinks[$po->id] = WhatsApp::shareLink($poSupplier->phone, PurchaseOrderController::supplierMessage($po, $poSupplier, $company));
                }
            }
        }
        $boqItems = BoqItem::where('project_id', $project->id)->orderBy('sort_order')->orderBy('id')->get();
        $paymentCertificates = PaymentCertificate::where('project_id', $project->id)->orderByDesc('certificate_number')->limit(5)->get();
        $paymentCertificateCount = PaymentCertificate::where('project_id', $project->id)->count();
        $subcontractModels = Subcontract::where('project_id', $project->id)->orderByDesc('created_at')->limit(5)->get();
        $subcontractCount = Subcontract::where('project_id', $project->id)->count();
        $subcontractSuppliers = Supplier::where('company_id', $project->company_id)->get()->keyBy('id');
        $subcontracts = $subcontractModels->map(fn (Subcontract $s) => [
            ...$s->toArray(),
            'supplier_name' => $subcontractSuppliers->get($s->supplier_id)->name ?? '—',
        ])->all();
        $allSubcontracts = Subcontract::where('project_id', $project->id)->get();
        $subcontractsContractValue = (float) $allSubcontracts->sum('contract_value');
        $subcontractsCumulativePaid = round($allSubcontracts->sum(fn (Subcontract $s) => $s->cumulativePaid()), 2);
        $subcontractsRetentionHeld = round($allSubcontracts->sum(fn (Subcontract $s) => $s->retentionHeld()), 2);
        $bankGuarantees = BankGuarantee::where('project_id', $project->id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get();
        $siteLogs = SiteLog::where('project_id', $project->id)
            ->orderByDesc('log_date')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();
        $siteLogCount = SiteLog::where('project_id', $project->id)->count();
        $punchListItems = PunchListItem::where('project_id', $project->id)
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at')
            ->get();
        $eotRequests = ExtensionOfTimeRequest::where('project_id', $project->id)->orderByDesc('created_at')->get();
        $eotUserIds = $eotRequests->pluck('requested_by')->merge($eotRequests->pluck('reviewed_by'))->filter()->unique();
        $eotUsers = User::whereIn('id', $eotUserIds)->get()->keyBy('id');
        $eotRequestRows = $eotRequests->map(fn (ExtensionOfTimeRequest $r) => [
            ...$r->toArray(),
            'requested_by_name' => $eotUsers->get($r->requested_by)->name ?? '—',
            'reviewed_by_name' => $r->reviewed_by ? ($eotUsers->get($r->reviewed_by)->name ?? '—') : null,
        ])->all();
        $approvedEotDays = (int) $eotRequests->where('status', 'approved')->sum('requested_days');
        $ldExposure = $project->ldExposure();
        $teamMembers = User::where('company_id', $project->company_id)->orderBy('name')->get();
        $actualCostTotal = (float) $vendorBills->sum('amount');
        $revisedBudget = (float) $project->budget + $approvedTotal;
        // Only 'issued' POs are real open commitments: a 'draft' PO isn't sent to the supplier yet, and a
        // 'received' one has already become a vendor bill counted in actualCostTotal above — counting it
        // here too would double-count the same money.
        $committedTotal = (float) $purchaseOrders->where('status', 'issued')->sum('total');
        $availableBudget = $revisedBudget - $actualCostTotal - $committedTotal;

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
            'purchaseOrders' => $purchaseOrders->toArray(),
            'purchaseOrderStatuses' => PurchaseOrder::STATUSES,
            'poWhatsappLinks' => $poWhatsappLinks,
            'whatsappApiConfigured' => WhatsApp::isConfigured(),
            'progressWhatsappLink' => $this->progressWhatsappLink($project, $client),
            'boqItems' => $boqItems->toArray(),
            'boqContractValue' => (float) $boqItems->sum('total'),
            'paymentCertificates' => $paymentCertificates->toArray(),
            'paymentCertificateCount' => $paymentCertificateCount,
            'cumulativeCertified' => (float) $paymentCertificates->max('cumulative_certified'),
            'retentionHeld' => $project->retentionHeld(),
            'subcontracts' => $subcontracts,
            'subcontractStatuses' => Subcontract::STATUSES,
            'subcontractCount' => $subcontractCount,
            'subcontractsContractValue' => $subcontractsContractValue,
            'subcontractsCumulativePaid' => $subcontractsCumulativePaid,
            'subcontractsRetentionHeld' => $subcontractsRetentionHeld,
            'eotRequests' => $eotRequestRows,
            'eotStatuses' => ExtensionOfTimeRequest::STATUSES,
            'approvedEotDays' => $approvedEotDays,
            'ldExposure' => $ldExposure,
            'bankGuarantees' => $bankGuarantees->toArray(),
            'bankGuaranteeTypes' => BankGuarantee::TYPES,
            'siteLogs' => $siteLogs->toArray(),
            'siteLogCount' => $siteLogCount,
            'punchListItems' => $punchListItems->toArray(),
            'punchListStatuses' => PunchListItem::STATUSES,
            'punchListPriorities' => PunchListItem::PRIORITIES,
            'teamMembers' => $teamMembers->toArray(),
            'actualCostTotal' => $actualCostTotal,
            'revisedBudget' => $revisedBudget,
            'committedTotal' => $committedTotal,
            'availableBudget' => $availableBudget,
            'budgetVariance' => $revisedBudget - $actualCostTotal,
            'budgetUsedPercent' => $revisedBudget > 0 ? min(999, round($actualCostTotal / $revisedBudget * 100)) : 0,
        ]);
    }

    /**
     * There's no client-facing project page (unlike estimates/invoices' share_token links), and
     * no tracked physical-progress percentage on Project either — so this reuses the same
     * budget-utilization number already surfaced on this page (see show()'s budgetUsedPercent)
     * as the closest existing stand-in for "how far along is this project", labeled honestly as
     * budget used rather than claimed as physical completion, plus $note: a short line the
     * sender types themselves, same as a person would actually message a client, rather than
     * inventing a new tracked-progress data model just for this message.
     */
    private function progressMessage(Project $project, Client $client, string $note = ''): string
    {
        $revisedBudget = $project->revisedBudget();
        $actual = $project->actualCostTotal();
        $percent = $revisedBudget > 0 ? min(999, round($actual / $revisedBudget * 100)) : 0;
        $statusLabel = ucfirst(str_replace('_', ' ', (string) $project->status));
        $message = "Hi {$client->name}, a quick update on \"{$project->name}\": status is {$statusLabel}, about {$percent}% of budget used so far.";
        return $note !== '' ? "{$message} {$note}" : $message;
    }

    private function progressWhatsappLink(Project $project, ?Client $client): ?string
    {
        if (!$client || empty($client->phone)) {
            return null;
        }
        return WhatsApp::shareLink($client->phone, $this->progressMessage($project, $client));
    }

    /** wa.me instant-link path with an optional sender-typed note — see progressMessage()'s docblock. */
    public function shareProgressWhatsApp(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);
        $client = $this->ownedClient($project->client_id, $project->company_id);
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.projects.no_client_phone'));
        }
        $note = trim((string) $request->input('note', ''));
        $link = WhatsApp::shareLink($client->phone, $this->progressMessage($project, $client, $note));
        if (!$link) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.projects.whatsapp_link_build_failed'));
        }
        return redirect()->away($link);
    }

    /** Real WhatsApp Business API send counterpart to shareProgressWhatsApp() above. */
    public function sendWhatsAppProgress(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);
        $client = $this->ownedClient($project->client_id, $project->company_id);
        if (!$client || empty($client->phone)) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.projects.no_client_phone'));
        }
        $note = trim((string) $request->input('note', ''));
        $result = WhatsApp::sendMessage($client->phone, $this->progressMessage($project, $client, $note));

        if (!empty($result['ok'])) {
            $this->flash('success', t('user.projects.whatsapp_progress_sent'));
        } else {
            $this->flash('error', t('user.projects.whatsapp_update_failed', ['error' => $result['error'] ?? json_encode($result['data'] ?? $result)]));
        }
        return redirect('/app/projects/' . $project->id);
    }

    public function duplicateForm(int $id): View
    {
        $source = $this->findOwned($id);
        $clients = Client::where('company_id', $source->company_id)->orderBy('name')->get();
        return view('app.projects.duplicate', [
            'project' => $source->toArray(),
            'clients' => $clients,
        ]);
    }

    public function duplicateProject(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $source = $this->findOwned($id);
        $companyId = $source->company_id;

        $name = trim((string) $request->input('name'));
        $newStartDate = $request->input('start_date') ?: null;

        // Preserve the source project's own overall duration (end - start) onto the new start
        // date, same span shifted forward — but only when the source actually has both dates to
        // compute a span from; guessing one from a partial pair would be worse than leaving it null.
        $newEndDate = null;
        if ($newStartDate && $source->start_date && $source->end_date) {
            $spanDays = $source->start_date->diffInDays($source->end_date);
            $newEndDate = date('Y-m-d', strtotime($newStartDate . " +{$spanDays} days"));
        }

        $project = Project::create([
            'company_id' => $companyId,
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $companyId)?->id ?? $source->client_id,
            'name' => $name !== '' ? $name : ($source->name . ' (Copy)'),
            'name_ar' => trim((string) $request->input('name_ar', '')) ?: $source->name_ar,
            'description' => $source->description,
            'description_ar' => $source->description_ar,
            // A duplicate is a brand-new job, regardless of how far along the source project was.
            'status' => 'planning',
            'budget' => $source->budget,
            'start_date' => $newStartDate,
            'end_date' => $newEndDate,
        ]);

        // Shift every schedule task by the same number of days the project's own start date
        // moved, so a standard build sequence slides forward onto the new timeline intact. If the
        // source project never had a start_date, there's no reference point for an offset — copy
        // the task dates unshifted rather than errorring or guessing one.
        $offsetDays = ($newStartDate && $source->start_date)
            ? $source->start_date->diffInDays($newStartDate, false)
            : 0;

        foreach (ScheduleTask::where('project_id', $source->id)->get() as $task) {
            ScheduleTask::create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'title' => $task->title,
                'title_ar' => $task->title_ar,
                'start_date' => $task->start_date ? $task->start_date->copy()->addDays($offsetDays)->format('Y-m-d') : null,
                'end_date' => $task->end_date ? $task->end_date->copy()->addDays($offsetDays)->format('Y-m-d') : null,
                // Every duplicated task starts fresh: not done yet, and not assigned to whoever
                // happened to be on the source project — same reasoning as Estimate::duplicate()
                // not copying its own "already happened" signature state.
                'status' => 'pending',
                'assigned_to' => null,
            ]);
        }

        $this->flash('success', t('user.projects.duplicated'));
        return redirect('/app/projects/' . $project->id);
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
        $newStatus = $request->input('status', 'planning');

        $data = [
            'client_id' => $this->ownedClient($request->input('client_id') ?: null, $project->company_id)?->id,
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'description' => $request->input('description', ''),
            'description_ar' => $request->input('description_ar', ''),
            'status' => $newStatus,
            'budget' => (float) $request->input('budget', 0),
            'advance_payment_amount' => (float) $request->input('advance_payment_amount', 0),
            'advance_recovery_percent' => $request->filled('advance_recovery_percent') ? min(100, max(0, (float) $request->input('advance_recovery_percent'))) : null,
            'start_date' => $request->input('start_date') ?: null,
            'end_date' => $request->input('end_date') ?: null,
            'defects_liability_end_date' => $request->input('defects_liability_end_date') ?: null,
            'ld_rate_per_day' => $request->filled('ld_rate_per_day') ? (float) $request->input('ld_rate_per_day') : null,
            'ld_cap_percent' => $request->filled('ld_cap_percent') ? min(100, max(0, (float) $request->input('ld_cap_percent'))) : null,
        ];

        // actual_completion_date is directly editable (a user can correct it or set it
        // retroactively), so an explicit value from the form always wins. Only when the field
        // is left blank AND the status is (becoming) 'completed' AND nothing is set yet do we
        // auto-populate it with today — same only-set-if-null pattern as certified_at elsewhere,
        // never overwriting a value that's already there.
        if ($request->filled('actual_completion_date')) {
            $data['actual_completion_date'] = $request->input('actual_completion_date');
        } elseif ($newStatus === 'completed' && !$project->actual_completion_date) {
            $data['actual_completion_date'] = now()->format('Y-m-d');
        }

        $project->update($data);

        $this->flash('success', t('user.projects.updated'));
        return redirect('/app/projects/' . $project->id);
    }

    /** Releases every unreleased-retention invoice on this project in one go — a convenience over releasing them one by one from each invoice's own page. */
    public function releaseAllRetention(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);

        $released = DB::transaction(function () use ($project) {
            $invoices = Invoice::where('project_id', $project->id)
                ->where('retention_amount', '>', 0)
                ->where('retention_released', false)
                ->get();
            foreach ($invoices as $invoice) {
                $invoice->update(['retention_released' => true, 'retention_released_at' => now()]);
            }
            return $invoices->count();
        });

        $this->flash('success', $released > 0
            ? t('user.projects.retention_released_all', ['count' => $released])
            : t('user.projects.retention_none_to_release'));
        return redirect('/app/projects/' . $project->id);
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwned($id);

        // Real financial history (an invoice or a vendor bill) makes a project part of the
        // company's books — deleting it would silently erase that record. Mark it Completed
        // instead; there's no DB-level FK protecting against this anywhere in this schema, so
        // this check is the only thing standing between a click and permanently lost financials.
        $invoiceCount = Invoice::where('project_id', $project->id)->count();
        $vendorBillCount = VendorBill::where('project_id', $project->id)->count();
        if ($invoiceCount > 0 || $vendorBillCount > 0) {
            $this->flash('error', t('user.projects.delete_blocked_financial', [
                'invoices' => $invoiceCount,
                'bills' => $vendorBillCount,
            ]));
            return redirect('/app/projects/' . $project->id);
        }

        // No invoices means no estimate from this project was ever converted to one either —
        // convertToInvoice() always stamps the new invoice with the source estimate's own
        // project_id, so zero invoices on the project guarantees zero converted estimates too.
        DB::transaction(function () use ($project) {
            $estimateIds = Estimate::where('project_id', $project->id)->pluck('id');
            EstimateItem::whereIn('estimate_id', $estimateIds)->delete();
            Estimate::where('project_id', $project->id)->delete();

            ChangeOrder::where('project_id', $project->id)->delete();

            $purchaseOrderIds = PurchaseOrder::where('project_id', $project->id)->pluck('id');
            PurchaseOrderItem::whereIn('purchase_order_id', $purchaseOrderIds)->delete();
            PurchaseOrder::where('project_id', $project->id)->delete();

            foreach (BankGuarantee::where('project_id', $project->id)->get() as $guarantee) {
                if ($guarantee->file_path) {
                    $file = public_path($guarantee->file_path);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            BankGuarantee::where('project_id', $project->id)->delete();

            SiteLog::where('project_id', $project->id)->delete();

            foreach (PunchListItem::where('project_id', $project->id)->get() as $item) {
                if ($item->photo_path) {
                    $file = public_path($item->photo_path);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            PunchListItem::where('project_id', $project->id)->delete();

            foreach (ProjectPhoto::where('project_id', $project->id)->get() as $photo) {
                if ($photo->file_path) {
                    $file = public_path($photo->file_path);
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            ProjectPhoto::where('project_id', $project->id)->delete();

            ScheduleTask::where('project_id', $project->id)->delete();

            $project->delete();
        });

        return $this->redirectWithFlash('/app/projects', 'success', t('user.projects.deleted_with_records'));
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
