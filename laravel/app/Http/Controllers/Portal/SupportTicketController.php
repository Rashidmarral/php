<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Support\TicketAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function index(): View
    {
        $client = Auth::guard('client')->user();
        $tickets = SupportTicket::where('channel', 'company')
            ->where('client_id', $client->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->get();

        return view('portal.support.index', [
            'tickets' => $tickets,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('portal.support.create', [
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $client = Auth::guard('client')->user();
        $subject = trim((string) $request->input('subject'));
        $message = trim((string) $request->input('message'));
        $category = array_key_exists((string) $request->input('category'), SupportTicket::CATEGORIES) ? $request->input('category') : 'general';

        if ($subject === '' || $message === '') {
            return $this->redirectWithFlash('/portal/support/new', 'error', 'Please enter a subject and describe your issue.');
        }

        $ticket = SupportTicket::create([
            'channel' => 'company',
            'company_id' => $client->company_id,
            'client_id' => $client->id,
            'subject' => $subject,
            'category' => $category,
            'priority' => 'normal',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $attachment = TicketAttachment::store($request->file('attachment'), $ticket->id);
        if ($attachment['error']) {
            return $this->redirectWithFlash('/portal/support/new', 'error', $attachment['error']);
        }

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'client',
            'sender_id' => $client->id,
            'sender_name' => $client->name,
            'message' => $message,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        $this->flash('success', 'Your message has been sent to the company — they will respond soon.');
        return redirect('/portal/support/' . $ticket->id);
    }

    public function show(int $id): View
    {
        $ticket = $this->findOwned($id);
        $ticket->load('messages');

        return view('portal.support.show', ['ticket' => $ticket]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->findOwned($id);
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return $this->redirectWithFlash('/portal/support/' . $id, 'error', 'Please enter a reply message.');
        }

        $attachment = TicketAttachment::store($request->file('attachment'), $ticket->id);
        if ($attachment['error']) {
            return $this->redirectWithFlash('/portal/support/' . $id, 'error', $attachment['error']);
        }

        $client = Auth::guard('client')->user();
        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'client',
            'sender_id' => $client->id,
            'sender_name' => $client->name,
            'message' => $message,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        $ticket->update(['last_message_at' => now(), 'status' => 'open']);

        $this->flash('success', 'Reply sent.');
        return redirect('/portal/support/' . $id);
    }

    private function findOwned(int $id): SupportTicket
    {
        $client = Auth::guard('client')->user();
        $ticket = SupportTicket::where('channel', 'company')->find($id);
        abort_if(!$ticket || $ticket->client_id !== $client->id, 404, 'Ticket not found.');
        return $ticket;
    }
}
