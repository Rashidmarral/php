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
use App\Models\Selection;
use App\Models\SelectionOption;
use App\Support\Moyasar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

    /**
     * This project's Selections (allowance tracking), grouped by category, each with its
     * options rendered as pickable cards — see chooseOption() for the client's pick action.
     */
    public function selections(int $projectId): View
    {
        $client = Auth::guard('client')->user();
        $project = Project::find($projectId);
        abort_if(!$project || $project->client_id !== $client->id, 404, 'Not found.');

        $selections = Selection::where('project_id', $project->id)
            ->with('options')
            ->orderBy('category')->orderBy('name')
            ->get();

        $grouped = [];
        foreach ($selections as $selection) {
            $grouped[$selection->category][] = [
                ...$selection->toArray(),
                'options' => $selection->options->toArray(),
                'chosen_option' => $selection->chosenOption()?->toArray(),
            ];
        }

        return view('portal.selections', [
            'pageTitle' => $project->name,
            'client' => $client,
            'project' => $project,
            'grouped' => $grouped,
            'statuses' => Selection::STATUSES,
        ]);
    }

    /**
     * The client picks one option for a selection: sets is_chosen on exactly that option
     * and clears it on every sibling option of the same selection (inside a transaction so
     * the swap is never left half-done), then moves the selection's status forward to
     * client_selected so the company sees it's ready to review/approve.
     */
    public function chooseOption(int $selectionId, int $optionId): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $selection = Selection::find($selectionId);
        abort_if(!$selection, 404, 'Not found.');
        $project = Project::find($selection->project_id);
        abort_if(!$project || $project->client_id !== $client->id, 404, 'Not found.');

        $option = SelectionOption::find($optionId);
        abort_if(!$option || $option->selection_id !== $selection->id, 404, 'Not found.');

        DB::transaction(function () use ($selection, $option) {
            SelectionOption::where('selection_id', $selection->id)->update(['is_chosen' => false]);
            $option->update(['is_chosen' => true]);
            $selection->update(['status' => 'client_selected']);
        });

        return $this->redirectWithFlash('/portal/projects/'.$selection->project_id.'/selections', 'success', t('portal.selections.choice_saved'));
    }
}
