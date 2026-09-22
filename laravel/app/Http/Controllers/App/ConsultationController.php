<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Consultation;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    public function index(): View
    {
        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);
        $plan = $company && $company->plan_id ? Plan::find($company->plan_id) : null;
        $quota = (int) ($plan->consultation_quota_monthly ?? 0);
        $used = Consultation::usedThisMonth($companyId);

        $consultations = Consultation::where('company_id', $companyId)->orderByDesc('created_at')->get()->toArray();

        return view('app.consultations.index', [
            'quota' => $quota,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'consultations' => $consultations,
            'planName' => $plan->name ?? null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }

        $companyId = Auth::user()->company_id;
        $company = Company::find($companyId);
        $plan = $company && $company->plan_id ? Plan::find($company->plan_id) : null;
        $quota = (int) ($plan->consultation_quota_monthly ?? 0);

        if ($quota <= 0) {
            return $this->redirectWithFlash('/app/billing', 'error', t('user.consultations.not_included_in_plan'));
        }
        if (Consultation::usedThisMonth($companyId) >= $quota) {
            return $this->redirectWithFlash('/app/consultations', 'error', $quota === 1 ? t('user.consultations.quota_used_one') : t('user.consultations.quota_used_many', ['quota' => $quota]));
        }

        $type = $request->input('type') === 'in_person' ? 'in_person' : 'chat';
        $topic = trim((string) $request->input('topic'));
        if ($topic === '') {
            return $this->redirectWithFlash('/app/consultations', 'error', t('user.consultations.topic_required'));
        }

        Consultation::create([
            'company_id' => $companyId,
            'requested_by' => Auth::id(),
            'type' => $type,
            'status' => 'requested',
            'topic' => $topic,
            'notes' => trim((string) $request->input('notes', '')),
            'preferred_date' => $request->input('preferred_date') ?: null,
        ]);

        $this->flash('success', t('user.consultations.request_sent'));
        return redirect('/app/consultations');
    }

    public function cancel(int $id): RedirectResponse
    {
        $consultation = Consultation::find($id);
        abort_if(!$consultation || $consultation->company_id !== Auth::user()->company_id, 404, 'Consultation not found.');

        if (in_array($consultation->status, ['requested', 'scheduled'], true)) {
            $consultation->update(['status' => 'cancelled']);
            $this->flash('success', t('user.consultations.cancelled'));
        }
        return redirect('/app/consultations');
    }
}
