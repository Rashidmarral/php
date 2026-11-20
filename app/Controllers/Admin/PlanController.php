<?php

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\Feature;
use App\Models\Plan;

class PlanController extends Controller
{
    public function index(): void
    {
        $plans = Plan::all('sort_order ASC');
        $this->view('admin/plans/index', ['pageTitle' => 'Subscription Plans', 'plans' => $plans], 'layouts/admin');
    }

    public function create(): void
    {
        $this->view('admin/plans/form', ['pageTitle' => 'New Plan', 'plan' => null, 'allFeatures' => Feature::ALL], 'layouts/admin');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        $this->save(null);
        Audit::log('plan_create', 'plan', null, trim((string) $this->input('name')));
        $this->flash('success', 'Plan created.');
        self::redirect('/admin/plans');
    }

    public function edit(string $id): void
    {
        $plan = Plan::find((int) $id);
        if (!$plan) {
            http_response_code(404);
            die('Plan not found.');
        }
        $this->view('admin/plans/form', ['pageTitle' => 'Edit Plan', 'plan' => $plan, 'allFeatures' => Feature::ALL], 'layouts/admin');
    }

    public function update(string $id): void
    {
        $this->verifyCsrf();
        $plan = Plan::find((int) $id);
        if (!$plan) {
            http_response_code(404);
            die('Plan not found.');
        }
        $this->save($plan['id']);
        Audit::log('plan_update', 'plan', $plan['id'], $plan['name']);
        $this->flash('success', 'Plan updated.');
        self::redirect('/admin/plans');
    }

    public function destroy(string $id): void
    {
        $this->verifyCsrf();
        $plan = Plan::find((int) $id);
        Plan::delete((int) $id);
        Audit::log('plan_delete', 'plan', (int) $id, $plan['name'] ?? '');
        $this->flash('success', 'Plan deleted.');
        self::redirect('/admin/plans');
    }

    private function save(?int $id): void
    {
        $featuresLines = array_filter(array_map('trim', explode("\n", (string) $this->input('features', ''))));

        $flags = [];
        foreach (array_keys(Feature::ALL) as $key) {
            $flags[$key] = $this->input("feature_{$key}") ? true : false;
        }

        $data = [
            'slug' => strtolower(trim((string) $this->input('slug'))),
            'name' => trim((string) $this->input('name')),
            'name_ar' => trim((string) $this->input('name_ar', '')),
            'tagline' => $this->input('tagline', ''),
            'tagline_ar' => $this->input('tagline_ar', ''),
            'price_monthly' => (float) $this->input('price_monthly', 0),
            'price_yearly' => (float) $this->input('price_yearly', 0),
            'max_users' => (int) $this->input('max_users', 5),
            'max_projects' => (int) $this->input('max_projects', 10),
            'consultation_quota_monthly' => (int) $this->input('consultation_quota_monthly', 0),
            'features' => json_encode(array_values($featuresLines)),
            'feature_flags' => json_encode($flags),
            'is_active' => $this->input('is_active') ? 1 : 0,
            'sort_order' => (int) $this->input('sort_order', 0),
        ];

        if ($id === null) {
            Plan::create($data);
        } else {
            Plan::update($id, $data);
        }
    }
}
