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
        if (is_string($plan)) {
            return $this->redirectWithFlash('/admin/plans/create', 'error', $plan);
        }
        AuditLog::record($request->user(), 'plan_create', 'plan', null, $plan->name);

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
        $result = $this->save($request, $plan->id);
        if (is_string($result)) {
            return $this->redirectWithFlash('/admin/plans/'.$plan->id.'/edit', 'error', $result);
        }
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

    /** Returns the saved Plan, or a translated error message string when validation fails. */
    private function save(Request $request, ?int $id): Plan|string
    {
        $name = trim((string) $request->input('name'));
        if ($name === '' || mb_strlen($name) > 100) {
            return t('admin.plan.name_invalid');
        }
        $nameAr = trim((string) $request->input('name_ar', ''));
        if (mb_strlen($nameAr) > 100) {
            return t('admin.plan.name_invalid');
        }

        $slug = strtolower(trim((string) $request->input('slug')));
        if ($slug === '' || mb_strlen($slug) > 50 || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1) {
            return t('admin.plan.slug_invalid');
        }
        $slugTaken = Plan::where('slug', $slug)->when($id !== null, fn ($q) => $q->where('id', '!=', $id))->exists();
        if ($slugTaken) {
            return t('admin.plan.slug_exists');
        }

        $tagline = mb_substr((string) $request->input('tagline', ''), 0, 255);
        $taglineAr = mb_substr((string) $request->input('tagline_ar', ''), 0, 255);

        $priceMonthly = $this->validatedNumberOrDefault($request->input('price_monthly', 0), 0, 99999999.99, 0.0);
        if ($priceMonthly === null) {
            return t('admin.plan.price_invalid');
        }
        $priceYearly = $this->validatedNumberOrDefault($request->input('price_yearly', 0), 0, 99999999.99, 0.0);
        if ($priceYearly === null) {
            return t('admin.plan.price_invalid');
        }
        $maxUsers = $this->validatedNumberOrDefault($request->input('max_users', 5), 1, 100000, 5.0);
        if ($maxUsers === null) {
            return t('admin.plan.max_users_invalid');
        }
        $maxProjects = $this->validatedNumberOrDefault($request->input('max_projects', 10), 1, 100000, 10.0);
        if ($maxProjects === null) {
            return t('admin.plan.max_projects_invalid');
        }
        $consultationQuota = $this->validatedNumberOrDefault($request->input('consultation_quota_monthly', 0), 0, 100000, 0.0);
        if ($consultationQuota === null) {
            return t('admin.plan.consultations_invalid');
        }
        $sortOrder = $this->validatedNumberOrDefault($request->input('sort_order', 0), 0, 99999, 0.0);
        if ($sortOrder === null) {
            return t('common.sort_order_invalid');
        }

        $featuresLines = array_filter(array_map('trim', explode("\n", (string) $request->input('features', ''))));

        $flags = [];
        foreach (array_keys(Feature::ALL) as $key) {
            $flags[$key] = (bool) $request->input("feature_{$key}");
        }

        $data = [
            'slug' => $slug,
            'name' => $name,
            'name_ar' => $nameAr,
            'tagline' => $tagline,
            'tagline_ar' => $taglineAr,
            'price_monthly' => $priceMonthly,
            'price_yearly' => $priceYearly,
            'max_users' => (int) $maxUsers,
            'max_projects' => (int) $maxProjects,
            'consultation_quota_monthly' => (int) $consultationQuota,
            'features' => json_encode(array_values($featuresLines)),
            'feature_flags' => json_encode($flags),
            'is_active' => (bool) $request->input('is_active'),
            'sort_order' => (int) $sortOrder,
        ];

        if ($id === null) {
            return Plan::create($data);
        }

        $plan = Plan::findOrFail($id);
        $plan->update($data);

        return $plan;
    }

    /** Rejects a non-numeric or out-of-range value instead of silently casting/clamping it — see App\Http\Controllers\App\ProjectController::validatedNumber() for the shared shape this mirrors. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected. */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return $this->validatedNumber($value, $min, $max);
    }
}
