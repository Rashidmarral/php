<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Project::where('company_id', $this->companyId())->orderByDesc('created_at')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== $this->companyId(), 404, 'Project not found.');
        return response()->json(['data' => $project]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'name_ar' => ['nullable', 'string', 'max:150'],
            'client_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:planning,in_progress,on_hold,completed'],
            'budget' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
        ]);

        $project = Project::create([
            'company_id' => $this->companyId(),
            'client_id' => $data['client_id'] ?? null,
            'name' => $data['name'],
            'name_ar' => $data['name_ar'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'planning',
            'budget' => $data['budget'] ?? 0,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
        ]);

        return response()->json(['data' => $project], 201);
    }
}
