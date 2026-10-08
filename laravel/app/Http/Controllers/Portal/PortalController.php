<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Estimate;
use App\Models\EstimateItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\ScheduleTask;
use App\Support\Moyasar;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function dashboard(): View
    {
        $client = Auth::guard('client')->user();
        $company = Company::find($client->company_id);

        return view('portal.dashboard', [
            'pageTitle' => 'My Projects',
            'client' => $client,
            'company' => $company,
            'projects' => Project::where('client_id', $client->id)->orderByDesc('created_at')->get(),
            'estimates' => Estimate::where('client_id', $client->id)->orderByDesc('created_at')->get(),
            'invoices' => Invoice::where('client_id', $client->id)->orderByDesc('created_at')->get(),
        ]);
    }

    public function project(int $id): View
    {
        $client = Auth::guard('client')->user();
        $project = Project::find($id);
        abort_if(!$project || $project->client_id !== $client->id, 404, 'Not found.');

        return view('portal.project', [
            'pageTitle' => $project->name,
            'client' => $client,
            'project' => $project,
            'tasks' => ScheduleTask::where('project_id', $project->id)->orderBy('start_date')->get(),
        ]);
    }

    public function estimate(int $id): View
    {
        $client = Auth::guard('client')->user();
        $estimate = Estimate::find($id);
        abort_if(!$estimate || $estimate->client_id !== $client->id, 404, 'Not found.');

        return view('portal.estimate', [
            'pageTitle' => $estimate->title,
            'client' => $client,
            'estimate' => $estimate,
            'items' => EstimateItem::where('estimate_id', $estimate->id)->orderBy('id')->get(),
        ]);
    }

    public function invoice(int $id): View
    {
        $client = Auth::guard('client')->user();
        $invoice = Invoice::find($id);
        abort_if(!$invoice || $invoice->client_id !== $client->id, 404, 'Not found.');
        $company = Company::find($invoice->company_id);

        return view('portal.invoice', [
            'pageTitle' => $invoice->invoice_number,
            'client' => $client,
            'invoice' => $invoice,
            'items' => InvoiceItem::where('invoice_id', $invoice->id)->orderBy('id')->get(),
            'company' => $company,
            'canPayOnline' => $invoice->status !== 'paid' && !empty($invoice->share_token) && Moyasar::isConfiguredForCompany($company?->toArray()),
        ]);
    }
}
