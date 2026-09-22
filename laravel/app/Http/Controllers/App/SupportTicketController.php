<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Support\Feature;
use App\Support\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = Auth::user()->company_id;
        $tab = $request->query('tab') === 'clients' ? 'clients' : 'mine';

        $tickets = SupportTicket::where('company_id', $companyId)
            ->where('channel', $tab === 'clients' ? 'company' : 'platform')
            ->with(['client', 'openedByUser'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        return view('app.support.index', [
            'tickets' => $tickets,
            'tab' => $tab,
            'statuses' => SupportTicket::STATUSES,
            'priorities' => SupportTicket::PRIORITIES,
            'hasPrioritySupport' => Feature::allows('priority_support'),
        ]);
    }

    public function create(): View
    {
        return view('app.support.create', [
            'categories' => SupportTicket::CATEGORIES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));
        $category = array_key_exists((string) $request->input('category'), SupportTicket::CATEGORIES) ? $request->input('category') : 'general';
        $priority = array_key_exists((string) $request->input('priority'), SupportTicket::PRIORITIES) ? $request->input('priority') : 'normal';

        if ($subject === '' || $message === '') {
            return $this->redirectWithFlash('/app/support/new', 'error', t('user.support_tickets.subject_message_required'));
        }

        $ticket = SupportTicket::create([
            'channel' => 'platform',
            'company_id' => Auth::user()->company_id,
            'opened_by_user_id' => Auth::id(),
            'subject' => $subject,
            'category' => $category,
            'priority' => $priority,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $attachment = TicketAttachment::store($request->file('attachment'), $ticket->id);
        if ($attachment['error']) {
            return $this->redirectWithFlash('/app/support/new', 'error', $attachment['error']);
        }

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'company',
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'message' => $message,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        $this->flash('success', t('user.support_tickets.submitted'));
        return redirect('/app/support/' . $ticket->id);
    }

    public function show(int $id): View
    {
        $ticket = $this->findOwned($id);
        $ticket->load(['messages', 'client', 'openedByUser']);

        return view('app.support.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->findOwned($id);
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return $this->redirectWithFlash('/app/support/' . $id, 'error', t('user.support_tickets.reply_message_required'));
        }

        $attachment = TicketAttachment::store($request->file('attachment'), $ticket->id);
        if ($attachment['error']) {
            return $this->redirectWithFlash('/app/support/' . $id, 'error', $attachment['error']);
        }

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'company',
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'message' => $message,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        $ticket->update([
            'last_message_at' => now(),
            'status' => $ticket->channel === 'platform' ? 'pending' : $ticket->status,
        ]);

        $this->flash('success', t('user.support_tickets.reply_sent'));
        return redirect('/app/support/' . $id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->findOwned($id);
        $status = $request->input('status');
        if (!array_key_exists($status, SupportTicket::STATUSES)) {
            return $this->redirectWithFlash('/app/support/' . $id, 'error', t('user.support_tickets.invalid_status'));
        }
        $ticket->update(['status' => $status]);
        $this->flash('success', t('user.support_tickets.status_updated'));
        return redirect('/app/support/' . $id);
    }

    private function findOwned(int $id): SupportTicket
    {
        $ticket = SupportTicket::find($id);
        abort_if(!$ticket || $ticket->company_id !== Auth::user()->company_id, 404, 'Ticket not found.');
        return $ticket;
    }
}
