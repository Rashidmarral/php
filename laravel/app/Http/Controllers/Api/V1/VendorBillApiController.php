<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use App\Models\VendorBill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorBillApiController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = VendorBill::where('company_id', $this->companyId());
        if ($request->filled('project_id')) {
            $query->where('project_id', (int) $request->input('project_id'));
        }
        return response()->json(['data' => $query->orderByDesc('bill_date')->get()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'category' => ['nullable', 'string', 'in:material,labor,equipment,subcontractor,other'],
            'bill_date' => ['nullable', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'integer'],
        ]);

        $project = Project::find($data['project_id']);
        abort_if(!$project || $project->company_id !== $this->companyId(), 404, 'Project not found.');

        $bill = VendorBill::create([
            'company_id' => $this->companyId(),
            'project_id' => $project->id,
            'supplier_id' => $data['supplier_id'] ?? null,
            'category' => $data['category'] ?? 'material',
            'description' => $data['description'],
            'amount' => $data['amount'],
            'bill_date' => $data['bill_date'] ?? now()->format('Y-m-d'),
            'reference' => $data['reference'] ?? null,
            'status' => 'unpaid',
        ]);

        return response()->json(['data' => $bill], 201);
    }
}
