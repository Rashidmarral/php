<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeadController extends Controller
{
    private const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];
    private const SOURCES = ['website', 'quick_estimate', 'referral', 'phone', 'walk_in', 'social_media', 'other'];

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        $companyId = Auth::user()->company_id;
        $status = (string) $request->input('status', '');
        $query = Lead::where('company_id', $companyId);
        if (in_array($status, self::STATUSES, true)) {
            $query->where('status', $status);
        }
        $leads = $query->orderByDesc('created_at')->get()->toArray();

        return view('app.leads.index', [
            'leads' => $leads,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'counts' => $this->statusCounts($companyId),
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        return view('app.leads.form', ['lead' => null, 'statuses' => self::STATUSES, 'sources' => self::SOURCES]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/leads/create', 'error', t('user.leads.name_required'));
        }
        [$contact, $contactError] = $this->contactFields($request);
        if ($contactError) {
            return $this->redirectWithFlash('/app/leads/create', 'error', $contactError);
        }
        $estimatedValue = $this->validatedNumberOrDefault($request->input('estimated_value', 0), 0, 9999999999.99, 0.0);
        if ($estimatedValue === null) {
            return $this->redirectWithFlash('/app/leads/create', 'error', t('user.leads.estimated_value_invalid'));
        }

        $lead = Lead::create([
            'company_id' => Auth::user()->company_id,
            'name' => mb_substr($name, 0, 150),
            'company_name' => mb_substr(trim((string) $request->input('company_name', '')), 0, 150),
            'company_name_ar' => mb_substr(trim((string) $request->input('company_name_ar', '')), 0, 150),
            ...$contact,
            'source' => in_array($request->input('source'), self::SOURCES, true) ? $request->input('source') : 'other',
            'status' => 'new',
            'estimated_value' => $estimatedValue,
            'notes' => (string) $request->input('notes', ''),
        ]);

        $this->flash('success', t('user.leads.added'));
        return redirect('/app/leads/' . $lead->id . '/edit');
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        return view('app.leads.form', ['lead' => $this->findOwned($id)->toArray(), 'statuses' => self::STATUSES, 'sources' => self::SOURCES]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $lead = $this->findOwned($id);
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/leads/' . $id . '/edit', 'error', t('user.leads.name_required'));
        }
        [$contact, $contactError] = $this->contactFields($request);
        if ($contactError) {
            return $this->redirectWithFlash('/app/leads/' . $id . '/edit', 'error', $contactError);
        }
        $estimatedValue = $this->validatedNumberOrDefault($request->input('estimated_value', 0), 0, 9999999999.99, 0.0);
        if ($estimatedValue === null) {
            return $this->redirectWithFlash('/app/leads/' . $id . '/edit', 'error', t('user.leads.estimated_value_invalid'));
        }

        $lead->update([
            'name' => mb_substr($name, 0, 150),
            'company_name' => mb_substr(trim((string) $request->input('company_name', '')), 0, 150),
            'company_name_ar' => mb_substr(trim((string) $request->input('company_name_ar', '')), 0, 150),
            ...$contact,
            'source' => in_array($request->input('source'), self::SOURCES, true) ? $request->input('source') : 'other',
            'status' => in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : $lead->status,
            'estimated_value' => $estimatedValue,
            'notes' => (string) $request->input('notes', ''),
        ]);

        $this->flash('success', t('user.leads.updated'));
        return redirect('/app/leads/' . $lead->id . '/edit');
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $lead = $this->findOwned($id);
        $status = (string) $request->input('status');
        if (in_array($status, self::STATUSES, true)) {
            $lead->update(['status' => $status]);
            $this->flash('success', t('user.leads.status_updated'));
        }
        return redirect('/app/leads');
    }

    public function convertToClient(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $lead = $this->findOwned($id);

        if (!empty($lead->converted_client_id)) {
            return redirect('/app/clients/' . $lead->converted_client_id . '/edit');
        }

        $client = Client::create([
            'company_id' => Auth::user()->company_id,
            'name' => $lead->company_name ?: $lead->name,
            'name_ar' => $lead->company_name_ar ?? '',
            'email' => $lead->email,
            'phone' => $lead->phone,
            'address' => '',
        ]);

        $lead->update(['status' => 'won', 'converted_client_id' => $client->id]);
        $this->flash('success', t('user.leads.converted'));
        return redirect('/app/clients/' . $client->id . '/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('leads')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', t('user.leads.deleted'));
        return redirect('/app/leads');
    }

    private function statusCounts(int $companyId): array
    {
        $rows = DB::table('leads')->where('company_id', $companyId)->select('status', DB::raw('COUNT(*) as c'))->groupBy('status')->get();
        $counts = array_fill_keys(self::STATUSES, 0);
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->c;
        }
        return $counts;
    }

    /**
     * Shared by store()/update(): the plain contact fields — previously
     * trimmed (store()) or not even trimmed (update()) and stored with no
     * format check at all. email is rejected outright when present but
     * malformed, the same filter_var(FILTER_VALIDATE_EMAIL) rule
     * TeamController::store()/AdminProfileController::update() already use
     * for their own email fields. phone is rejected when present but not a
     * recognizable Saudi mobile number, via the shared
     * App\Support\PhoneNumber::isValidSaudiMobile() — the same
     * +966 5x xxx xxxx format this form's own placeholder has always shown.
     *
     * @return array{0: array<string, string>, 1: ?string} [fields, errorMessage]
     */
    private function contactFields(Request $request): array
    {
        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [[], t('common.email_invalid')];
        }

        $phone = trim((string) $request->input('phone', ''));
        if (!PhoneNumber::isValidSaudiMobile($phone)) {
            return [[], t('common.phone_invalid')];
        }

        return [[
            'email' => mb_substr($email, 0, 150),
            'phone' => mb_substr($phone, 0, 30),
        ], null];
    }

    /** Rejects a non-numeric or out-of-[$min,$max] value outright; returns the float otherwise. Mirrors EstimateController/InvoiceController's own helper of the same name. */
    private function validatedNumber(mixed $value, float $min, float $max): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }
        $float = (float) $value;

        return ($float >= $min && $float <= $max) ? $float : null;
    }

    /** Same as validatedNumber(), but a blank/missing value is treated as $default rather than rejected — used for estimated_value, where clearing the input has always meant "none/zero". */
    private function validatedNumberOrDefault(mixed $value, float $min, float $max, float $default): ?float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return $this->validatedNumber($value, $min, $max);
    }

    private function findOwned(int $id): Lead
    {
        $lead = Lead::find($id);
        abort_if(!$lead || $lead->company_id !== Auth::user()->company_id, 404, 'Lead not found.');
        return $lead;
    }
}
