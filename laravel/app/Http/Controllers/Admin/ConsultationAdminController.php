<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Consultation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ConsultationAdminController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        $consultations = Consultation::query()
            ->join('companies as c', 'c.id', '=', 'consultations.company_id')
            ->leftJoin('users as u', 'u.id', '=', 'consultations.requested_by')
            ->when(in_array($status, Consultation::STATUSES, true), fn ($q) => $q->where('consultations.status', $status))
            ->orderByDesc(DB::raw("(consultations.status = 'requested')"))
            ->orderByDesc('consultations.created_at')
            ->select('consultations.*', 'c.name as company_name', 'u.name as requested_by_name')
            ->paginate($this->perPage($request))
            ->withQueryString();

        return view('admin.consultations.index', [
            'consultations' => $consultations,
            'statusFilter' => $status,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $consultation = Consultation::findOrFail($id);

        $status = (string) $request->input('status', $consultation->status);
        if (!in_array($status, Consultation::STATUSES, true)) {
            $status = $consultation->status;
        }

        $consultation->update([
            'status' => $status,
            'assigned_engineer' => trim((string) $request->input('assigned_engineer', '')),
            'scheduled_at' => $request->input('scheduled_at') ?: null,
            'admin_notes' => trim((string) $request->input('admin_notes', '')),
        ]);
        AuditLog::record($request->user(), 'consultation_update', 'consultation', $consultation->id, "{$consultation->topic} → {$status}");

        return $this->redirectWithFlash('/admin/consultations', 'success', 'Consultation updated.');
    }
}
