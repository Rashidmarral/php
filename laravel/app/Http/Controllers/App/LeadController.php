<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Lead;
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
            return $this->redirectWithFlash('/app/leads/create', 'error', 'Lead name is required.');
        }

        $lead = Lead::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'company_name' => trim((string) $request->input('company_name', '')),
            'company_name_ar' => trim((string) $request->input('company_name_ar', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'source' => in_array($request->input('source'), self::SOURCES, true) ? $request->input('source') : 'other',
            'status' => 'new',
            'estimated_value' => (float) $request->input('estimated_value', 0),
            'notes' => (string) $request->input('notes', ''),
        ]);

        $this->flash('success', 'Lead added.');
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

        $lead->update([
            'name' => trim((string) $request->input('name')),
            'company_name' => trim((string) $request->input('company_name', '')),
            'company_name_ar' => trim((string) $request->input('company_name_ar', '')),
            'email' => trim((string) $request->input('email', '')),
            'phone' => trim((string) $request->input('phone', '')),
            'source' => in_array($request->input('source'), self::SOURCES, true) ? $request->input('source') : 'other',
            'status' => in_array($request->input('status'), self::STATUSES, true) ? $request->input('status') : $lead->status,
            'estimated_value' => (float) $request->input('estimated_value', 0),
            'notes' => (string) $request->input('notes', ''),
        ]);

        $this->flash('success', 'Lead updated.');
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
            $this->flash('success', 'Lead status updated.');
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
        $this->flash('success', 'Lead converted to a client.');
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
        $this->flash('success', 'Lead deleted.');
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

    private function findOwned(int $id): Lead
    {
        $lead = Lead::find($id);
        abort_if(!$lead || $lead->company_id !== Auth::user()->company_id, 404, 'Lead not found.');
        return $lead;
    }
}
