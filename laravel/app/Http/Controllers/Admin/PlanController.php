<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Support\Feature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        return view('admin.plans.index', ['plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function create(): View
    {
        return view('admin.plans.form', ['plan' => null, 'allFeatures' => Feature::ALL]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = $this->save($request, null);
        AuditLog::record($request->user(), 'plan_create', 'plan', null, trim((string) $request->input('name')));
        return $this->redirectWithFlash('/admin/plans', 'success', t('admin.plans.created'));
    }

    public function edit(int $id): View
    {
        $plan = Plan::findOrFail($id);
        return view('admin.plans.form', ['plan' => $plan, 'allFeatures' => Feature::ALL]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);
        $this->save($request, $plan->id);
        AuditLog::record($request->user(), 'plan_update', 'plan', $plan->id, $plan->name);
        return $this->redirectWithFlash('/admin/plans', 'success', t('admin.plans.updated'));
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $plan = Plan::find($id);
        Plan::destroy($id);
        AuditLog::record($request->user(), 'plan_delete', 'plan', $id, $plan->name ?? '');
        return $this->redirectWithFlash('/admin/plans', 'success', t('admin.plans.deleted'));
    }

    private function save(Request $request, ?int $id): Plan
    {
        $featuresLines = array_filter(array_map('trim', explode("\n", (string) $request->input('features', ''))));

        $flags = [];
        foreach (array_keys(Feature::ALL) as $key) {
            $flags[$key] = (bool) $request->input("feature_{$key}");
        }

        $data = [
            'slug' => strtolower(trim((string) $request->input('slug'))),
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'tagline' => $request->input('tagline', ''),
            'tagline_ar' => $request->input('tagline_ar', ''),
            'price_monthly' => (float) $request->input('price_monthly', 0),
            'price_yearly' => (float) $request->input('price_yearly', 0),
            'max_users' => (int) $request->input('max_users', 5),
            'max_projects' => (int) $request->input('max_projects', 10),
            'consultation_quota_monthly' => (int) $request->input('consultation_quota_monthly', 0),
            'features' => json_encode(array_values($featuresLines)),
            'feature_flags' => json_encode($flags),
            'is_active' => (bool) $request->input('is_active'),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];

        if ($id === null) {
            return Plan::create($data);
        }

        $plan = Plan::findOrFail($id);
        $plan->update($data);
        return $plan;
    }
}
