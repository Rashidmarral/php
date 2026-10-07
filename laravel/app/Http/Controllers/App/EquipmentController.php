<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\EquipmentMaintenanceLog;
use App\Models\Project;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Equipment/fleet asset register — the real counterpart to the cost-classification-only
 * "equipment" category (VendorBill::CATEGORIES / EstimateItem.item_type). Built on the same
 * "register entity with a documents/history sub-list" shape as SupplierController, with two
 * history sub-lists instead of one: a maintenance log (see EquipmentMaintenanceLog) and an
 * assignment/utilization history against projects (see EquipmentAssignment).
 */
class EquipmentController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        $query = Equipment::where('company_id', Auth::user()->company_id);

        $status = $request->query('status');
        if ($status && array_key_exists($status, Equipment::STATUSES)) {
            $query->where('status', $status);
        }
        $category = trim((string) $request->query('category', ''));
        if ($category !== '') {
            $query->where('category', $category);
        }

        $equipment = $query->orderBy('name')->get();
        $categories = Equipment::where('company_id', Auth::user()->company_id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('app.equipment.index', [
            'equipment' => $equipment->toArray(),
            'statuses' => Equipment::STATUSES,
            'categories' => $categories,
            'filterStatus' => $status,
            'filterCategory' => $category,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        return view('app.equipment.form', [
            'equipment' => null,
            'suppliers' => Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/equipment/create', 'error', t('user.equipment.name_required'));
        }

        $equipment = Equipment::create([
            'company_id' => Auth::user()->company_id,
            ...$this->fields($request),
        ]);

        $this->flash('success', t('user.equipment.added'));
        return redirect('/app/equipment/' . $equipment->id);
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        $equipment = $this->findOwned($id);

        $maintenanceLogs = $equipment->maintenanceLogs()->get()->toArray();

        $assignmentModels = $equipment->assignments()->get();
        $projectNames = Project::where('company_id', $equipment->company_id)
            ->whereIn('id', $assignmentModels->pluck('project_id')->unique())
            ->get()
            ->keyBy('id');
        $assignments = $assignmentModels->map(fn (EquipmentAssignment $a) => [
            ...$a->toArray(),
            'project_name' => $projectNames->get($a->project_id)->name ?? '—',
        ])->all();

        $activeAssignment = $equipment->activeAssignment();
        $activeAssignmentRow = $activeAssignment ? [
            ...$activeAssignment->toArray(),
            'project_name' => $projectNames->get($activeAssignment->project_id)->name ?? '—',
        ] : null;

        return view('app.equipment.show', [
            'equipment' => $equipment->toArray(),
            'statuses' => Equipment::STATUSES,
            'ownershipTypes' => Equipment::OWNERSHIP_TYPES,
            'supplier' => $equipment->supplier_id ? $this->ownedSupplier($equipment->supplier_id, $equipment->company_id) : null,
            'maintenanceLogs' => $maintenanceLogs,
            'assignments' => $assignments,
            'activeAssignment' => $activeAssignmentRow,
            'projects' => Project::where('company_id', $equipment->company_id)->orderBy('name')->get()->toArray(),
            'hasHistory' => !empty($maintenanceLogs) || !empty($assignments),
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        return view('app.equipment.form', [
            'equipment' => $this->findOwned($id)->toArray(),
            'suppliers' => Supplier::where('company_id', Auth::user()->company_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $equipment = $this->findOwned($id);
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/equipment/' . $equipment->id . '/edit', 'error', t('user.equipment.name_required'));
        }

        $equipment->update($this->fields($request));

        $this->flash('success', t('user.equipment.updated'));
        return redirect('/app/equipment/' . $equipment->id);
    }

    /** Blocked once this asset has any maintenance or assignment history — same "keep the record" rule ProjectController::destroy() applies to financial history. */
    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $equipment = $this->findOwned($id);
        if ($equipment->hasHistory()) {
            return $this->redirectWithFlash('/app/equipment/' . $equipment->id, 'error', t('user.equipment.delete_blocked_history'));
        }
        $equipment->delete();
        $this->flash('success', t('user.equipment.removed'));
        return redirect('/app/equipment');
    }

    public function storeMaintenanceLog(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $equipment = $this->findOwned($id);

        $maintenanceDate = $request->input('maintenance_date') ?: now()->format('Y-m-d');
        $description = trim((string) $request->input('description'));
        if ($description === '') {
            return $this->redirectWithFlash('/app/equipment/' . $equipment->id, 'error', t('user.equipment.maintenance_description_required'));
        }

        EquipmentMaintenanceLog::create([
            'company_id' => $equipment->company_id,
            'equipment_id' => $equipment->id,
            'maintenance_date' => $maintenanceDate,
            'description' => $description,
            'cost' => $request->input('cost') !== null && $request->input('cost') !== '' ? (float) $request->input('cost') : null,
            'performed_by' => trim((string) $request->input('performed_by', '')) ?: null,
            'next_due_date' => $request->input('next_due_date') ?: null,
        ]);

        $this->flash('success', t('user.equipment.maintenance_added'));
        return redirect('/app/equipment/' . $equipment->id);
    }

    /** Raised from the asset's own page — project chosen from a dropdown. */
    public function storeAssignment(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $equipment = $this->findOwned($id);
        return $this->createAssignment(
            $request,
            $equipment,
            $this->ownedProjectId($request->input('project_id'), $equipment->company_id),
            '/app/equipment/' . $equipment->id
        );
    }

    /** Raised from a project's own show page — equipment chosen from a dropdown. */
    public function assignToProject(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $equipment = $this->ownedEquipment($request->input('equipment_id'), $project->company_id);
        if (!$equipment) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.equipment.asset_required'));
        }
        return $this->createAssignment($request, $equipment, $project->id, '/app/projects/' . $project->id);
    }

    /** Sets returned_date = now() and flips the asset's status back to available. */
    public function returnAssignment(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('equipment_management')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $assignment = $this->findOwnedAssignment($id);
        $equipment = $this->findOwned($assignment->equipment_id);
        $redirectPath = request()->input('from') === 'project'
            ? '/app/projects/' . $assignment->project_id
            : '/app/equipment/' . $equipment->id;

        if (!$assignment->isActive()) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.equipment.already_returned'));
        }

        $assignment->update(['returned_date' => now()->format('Y-m-d')]);
        if ($equipment->status === 'in_use') {
            $equipment->update(['status' => 'available']);
        }

        $this->flash('success', t('user.equipment.returned'));
        return redirect($redirectPath);
    }

    /** Shared by store()/update(): the asset fields, normalized the same way for both. */
    private function fields(Request $request): array
    {
        $ownershipType = $request->input('ownership_type');
        $status = $request->input('status');
        return [
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')) ?: null,
            'asset_number' => trim((string) $request->input('asset_number', '')) ?: null,
            'category' => trim((string) $request->input('category', '')) ?: null,
            'ownership_type' => array_key_exists($ownershipType, Equipment::OWNERSHIP_TYPES) ? $ownershipType : 'owned',
            'purchase_date' => $request->input('purchase_date') ?: null,
            'purchase_cost' => $request->input('purchase_cost') !== null && $request->input('purchase_cost') !== '' ? (float) $request->input('purchase_cost') : null,
            'rental_cost_per_day' => $request->input('rental_cost_per_day') !== null && $request->input('rental_cost_per_day') !== '' ? (float) $request->input('rental_cost_per_day') : null,
            'status' => array_key_exists($status, Equipment::STATUSES) ? $status : 'available',
            'notes' => trim((string) $request->input('notes', '')) ?: null,
            'supplier_id' => $this->ownedSupplierId($request->input('supplier_id'), Auth::user()->company_id),
        ];
    }

    /**
     * Shared by storeAssignment()/assignToProject(): creates the assignment once a
     * no-other-active-assignment check passes, and flips the asset to in_use. Rejects with a
     * clear error if this asset is already assigned elsewhere (unreturned) or retired.
     */
    private function createAssignment(Request $request, Equipment $equipment, ?int $projectId, string $redirectPath): RedirectResponse
    {
        if (!$projectId) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.equipment.project_required'));
        }
        if ($equipment->status === 'retired') {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.equipment.cannot_assign_retired'));
        }
        // A single asset can't be in two places at once — reject a second concurrent
        // assignment while an earlier one is still unreturned.
        if ($equipment->activeAssignment()) {
            return $this->redirectWithFlash($redirectPath, 'error', t('user.equipment.already_assigned'));
        }

        EquipmentAssignment::create([
            'company_id' => $equipment->company_id,
            'equipment_id' => $equipment->id,
            'project_id' => $projectId,
            'assigned_date' => $request->input('assigned_date') ?: now()->format('Y-m-d'),
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ]);
        $equipment->update(['status' => 'in_use']);

        $this->flash('success', t('user.equipment.assigned'));
        return redirect($redirectPath);
    }

    /** Only returns the supplier id if it belongs to $companyId — never trust a raw supplier_id from the request. */
    private function ownedSupplierId(mixed $id, int $companyId): ?int
    {
        $supplier = $this->ownedSupplier($id, $companyId);
        return $supplier?->id;
    }

    private function ownedSupplier(mixed $id, int $companyId): ?Supplier
    {
        if (!$id) {
            return null;
        }
        $supplier = Supplier::find((int) $id);
        return ($supplier && $supplier->company_id === $companyId) ? $supplier : null;
    }

    /** Only returns the project id if it belongs to $companyId — never trust a raw project_id from the request. */
    private function ownedProjectId(mixed $id, int $companyId): ?int
    {
        if (!$id) {
            return null;
        }
        $project = Project::find((int) $id);
        return ($project && $project->company_id === $companyId) ? $project->id : null;
    }

    /** Only returns equipment belonging to $companyId — never trust a raw equipment_id from the request. */
    private function ownedEquipment(mixed $id, int $companyId): ?Equipment
    {
        if (!$id) {
            return null;
        }
        $equipment = Equipment::find((int) $id);
        return ($equipment && $equipment->company_id === $companyId) ? $equipment : null;
    }

    private function findOwned(int $id): Equipment
    {
        $equipment = Equipment::find($id);
        abort_if(!$equipment || $equipment->company_id !== Auth::user()->company_id, 404, 'Equipment not found.');
        return $equipment;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }

    private function findOwnedAssignment(int $id): EquipmentAssignment
    {
        $assignment = EquipmentAssignment::find($id);
        abort_if(!$assignment || $assignment->company_id !== Auth::user()->company_id, 404, 'Equipment assignment not found.');
        return $assignment;
    }
}
