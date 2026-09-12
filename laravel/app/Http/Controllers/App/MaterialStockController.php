<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MaterialStockController extends Controller
{
    public function show(int $materialId): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        $material = $this->findOwned($materialId);
        $companyId = Auth::user()->company_id;

        $movements = MaterialStockMovement::where('material_id', $material->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
        $projectIds = $movements->pluck('project_id')->filter()->unique();
        $projects = Project::where('company_id', $companyId)->whereIn('id', $projectIds)->pluck('name', 'id');

        return view('app.materials.stock', [
            'material' => $material->toArray(),
            'movements' => $movements->toArray(),
            'projectNames' => $projects,
            'allProjects' => Project::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'types' => MaterialStockMovement::TYPES,
            'directions' => MaterialStockMovement::DIRECTIONS,
        ]);
    }

    public function recordMovement(Request $request, int $materialId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('materials')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $material = $this->findOwned($materialId);
        $companyId = Auth::user()->company_id;
        $backTo = '/app/materials/' . $material->id . '/stock';

        $type = (string) $request->input('type');
        if (!array_key_exists($type, MaterialStockMovement::TYPES)) {
            return $this->redirectWithFlash($backTo, 'error', 'Choose a valid movement type.');
        }

        $qty = (float) $request->input('qty', 0);
        if ($qty <= 0) {
            return $this->redirectWithFlash($backTo, 'error', 'Quantity must be a positive number.');
        }

        $direction = null;
        if ($type === 'adjustment') {
            $direction = (string) $request->input('direction');
            if (!array_key_exists($direction, MaterialStockMovement::DIRECTIONS)) {
                return $this->redirectWithFlash($backTo, 'error', 'Choose whether the adjustment goes up or down.');
            }
        }

        // Never trust a raw project_id from the request — only attach it if it's a real
        // project belonging to this company, same pattern as PurchaseOrderController's
        // ownedSupplier(): silently drop a cross-tenant/unknown id rather than 500 or leak it.
        $projectId = $this->ownedProjectId($request->input('project_id') ?: null, $companyId);
        $note = trim((string) $request->input('note', ''));

        $result = DB::transaction(function () use ($material, $type, $qty, $direction, $projectId, $note, $companyId) {
            // Lock the material row for the duration of the transaction so two concurrent
            // movements against the same material can't both read the same qty_on_hand and
            // push it negative.
            $locked = Material::where('id', $material->id)->lockForUpdate()->first();
            $delta = match (true) {
                $type === 'receive' => $qty,
                $type === 'issue' => -$qty,
                $direction === 'down' => -$qty,
                default => $qty,
            };
            $newQty = (float) $locked->qty_on_hand + $delta;
            if ($newQty < 0) {
                return null;
            }

            $movement = MaterialStockMovement::create([
                'company_id' => $companyId,
                'material_id' => $locked->id,
                'type' => $type,
                'qty' => $qty,
                'direction' => $direction,
                'project_id' => $projectId,
                'note' => $note !== '' ? $note : null,
                'created_by' => Auth::id(),
            ]);
            $locked->update(['qty_on_hand' => $newQty]);
            return $movement;
        });

        if ($result === null) {
            $onHand = number_format((float) $material->qty_on_hand, 2);
            return $this->redirectWithFlash($backTo, 'error', "Not enough stock on hand for that — only {$onHand} {$material->unit} available.");
        }

        $this->flash('success', 'Stock movement recorded.');
        return redirect($backTo);
    }

    private function findOwned(int $id): Material
    {
        $material = Material::find($id);
        abort_if(!$material || $material->company_id !== Auth::user()->company_id, 404, 'Material not found.');
        return $material;
    }

    /** Only returns the project id if it belongs to $companyId — never trust a raw project_id from the request. */
    private function ownedProjectId(?int $id, int $companyId): ?int
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project->id : null;
    }
}
