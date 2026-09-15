<?php

namespace App\Controllers\User;

use App\Core\Auth;
use App\Core\Controller;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Plan;

class ConsultationController extends Controller
{
    public function index(): void
    {
        $companyId = Auth::companyId();
        $company = Company::find($companyId);
        $plan = $company && $company['plan_id'] ? Plan::find((int) $company['plan_id']) : null;
        $quota = (int) ($plan['consultation_quota_monthly'] ?? 0);
        $used = Consultation::usedThisMonth($companyId);

        $consultations = Consultation::query(
            'SELECT * FROM consultations WHERE company_id = ? ORDER BY created_at DESC',
            [$companyId]
        )->fetchAll();

        $this->view('user/consultations/index', [
            'pageTitle' => 'Expert Consultation',
            'quota' => $quota,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'consultations' => $consultations,
            'planName' => $plan['name'] ?? null,
        ], 'layouts/app');
    }

    public function store(): void
    {
        $this->verifyCsrf();
        Auth::requireAbility('write');

        $companyId = Auth::companyId();
        $company = Company::find($companyId);
        $plan = $company && $company['plan_id'] ? Plan::find((int) $company['plan_id']) : null;
        $quota = (int) ($plan['consultation_quota_monthly'] ?? 0);

        if ($quota <= 0) {
            $this->flash('error', 'Live expert consultations aren\'t included in your current plan. Upgrade to unlock them.');
            self::redirect('/app/billing');
        }
        if (Consultation::usedThisMonth($companyId) >= $quota) {
            $this->flash('error', "You've used all {$quota} consultation" . ($quota === 1 ? '' : 's') . " included in your plan this month. More become available next month, or upgrade your plan for a higher allowance.");
            self::redirect('/app/consultations');
        }

        $type = $this->input('type') === 'in_person' ? 'in_person' : 'chat';
        $topic = trim((string) $this->input('topic'));
        if ($topic === '') {
            $this->flash('error', 'Briefly describe what you\'d like to discuss.');
            self::redirect('/app/consultations');
        }

        Consultation::create([
            'company_id' => $companyId,
            'requested_by' => Auth::user()['id'],
            'type' => $type,
            'status' => 'requested',
            'topic' => $topic,
            'notes' => trim((string) $this->input('notes', '')),
            'preferred_date' => $this->input('preferred_date') ?: null,
        ]);

        $this->flash('success', "Request sent — one of our engineers will confirm a time with you shortly.");
        self::redirect('/app/consultations');
    }

    public function cancel(string $id): void
    {
        $this->verifyCsrf();
        $consultation = Consultation::find((int) $id);
        if (!$consultation || (int) $consultation['company_id'] !== Auth::companyId()) {
            http_response_code(404);
            die('Consultation not found.');
        }
        if (in_array($consultation['status'], ['requested', 'scheduled'], true)) {
            Consultation::update($consultation['id'], ['status' => 'cancelled']);
            $this->flash('success', 'Consultation cancelled.');
        }
        self::redirect('/app/consultations');
    }
}
