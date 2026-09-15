<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Estimate;
use App\Models\EstimateItem;
use Illuminate\Http\JsonResponse;

class EstimateApiController extends ApiController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Estimate::where('company_id', $this->companyId())->orderByDesc('created_at')->get()]);
    }

    public function show(int $id): JsonResponse
    {
        $estimate = Estimate::find($id);
        abort_if(!$estimate || $estimate->company_id !== $this->companyId(), 404, 'Estimate not found.');

        return response()->json([
            'data' => $estimate,
            'items' => EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get(),
        ]);
    }
}
