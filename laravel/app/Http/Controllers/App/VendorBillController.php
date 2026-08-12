<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\VendorBill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VendorBillController extends Controller
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png'];

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);
        $companyId = Auth::user()->company_id;

        $description = trim((string) $request->input('description'));
        $amount = (float) $request->input('amount', 0);
        if ($description === '' || $amount <= 0) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'A description and a positive amount are required.');
        }

        $filePath = null;
        $file = $request->file('receipt');
        if ($file && $file->isValid()) {
            $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXT, true)) {
                return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', 'Receipt file type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXT));
            }
            $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
            $file->move(public_path("uploads/vendor-bills/{$companyId}"), $storedName);
            $filePath = "/uploads/vendor-bills/{$companyId}/{$storedName}";
        }

        VendorBill::create([
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'supplier_id' => $request->input('supplier_id') ?: null,
            'category' => in_array($request->input('category'), array_keys(VendorBill::CATEGORIES), true) ? $request->input('category') : 'material',
            'description' => $description,
            'amount' => $amount,
            'bill_date' => $request->input('bill_date') ?: now()->format('Y-m-d'),
            'reference' => trim((string) $request->input('reference', '')),
            'status' => in_array($request->input('status'), ['unpaid', 'paid'], true) ? $request->input('status') : 'unpaid',
            'file_path' => $filePath,
        ]);

        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', 'Vendor bill recorded.');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $bill = $this->findOwned($id);
        $projectId = $bill->project_id;
        if ($bill->file_path) {
            $file = public_path($bill->file_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $bill->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', 'Vendor bill removed.');
    }

    private function findOwned(int $id): VendorBill
    {
        $bill = VendorBill::find($id);
        abort_if(!$bill || $bill->company_id !== Auth::user()->company_id, 404, 'Vendor bill not found.');
        return $bill;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
