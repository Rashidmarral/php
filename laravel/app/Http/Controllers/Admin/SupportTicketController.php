<?php

namespace App\Http\Controllers\Admin;

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
    public function index(Request $request): View
    {
        $status = $request->query('status', '');
        $query = SupportTicket::where('channel', 'platform')->with(['company', 'openedByUser']);
        if ($status !== '' && array_key_exists($status, SupportTicket::STATUSES)) {
            $query->where('status', $status);
        }

        return view('admin.support.index', [
            'tickets' => $query->orderByRaw("FIELD(status,'open','pending','resolved','closed')")->orderByDesc('last_message_at')->get(),
            'statuses' => SupportTicket::STATUSES,
            'priorities' => SupportTicket::PRIORITIES,
            'activeStatus' => $status,
            'openCount' => SupportTicket::where('channel', 'platform')->whereIn('status', ['open', 'pending'])->count(),
        ]);
    }

    public function show(int $id): View
    {
        $ticket = $this->findOwned($id);
        $ticket->load(['messages', 'company', 'openedByUser']);

        return view('admin.support.show', [
            'ticket' => $ticket,
            'statuses' => SupportTicket::STATUSES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    public function reply(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->findOwned($id);
        $message = trim((string) $request->input('message'));
        if ($message === '') {
            return $this->redirectWithFlash('/admin/support/' . $id, 'error', 'Please enter a reply message.');
        }

        $attachment = TicketAttachment::store($request->file('attachment'), $ticket->id);
        if ($attachment['error']) {
            return $this->redirectWithFlash('/admin/support/' . $id, 'error', $attachment['error']);
        }

        SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'sender_type' => 'admin',
            'sender_id' => Auth::id(),
            'sender_name' => Auth::user()->name,
            'message' => $message,
            'attachment_path' => $attachment['path'],
            'attachment_name' => $attachment['name'],
        ]);

        $ticket->update(['last_message_at' => now(), 'status' => 'pending']);

        $this->flash('success', 'Reply sent.');
        return redirect('/admin/support/' . $id);
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $ticket = $this->findOwned($id);
        $data = [];
        if (array_key_exists($request->input('status'), SupportTicket::STATUSES)) {
            $data['status'] = $request->input('status');
        }
        if (array_key_exists($request->input('priority'), SupportTicket::PRIORITIES)) {
            $data['priority'] = $request->input('priority');
        }
        if (empty($data)) {
            return $this->redirectWithFlash('/admin/support/' . $id, 'error', 'Nothing to update.');
        }
        $ticket->update($data);
        $this->flash('success', 'Ticket updated.');
        return redirect('/admin/support/' . $id);
    }

    private function findOwned(int $id): SupportTicket
    {
        $ticket = SupportTicket::where('channel', 'platform')->find($id);
        abort_if(!$ticket, 404, 'Ticket not found.');
        return $ticket;
    }
}
