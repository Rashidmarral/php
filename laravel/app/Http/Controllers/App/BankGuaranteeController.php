<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\BankGuarantee;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BankGuaranteeController extends Controller
{
    private const ALLOWED_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public function store(Request $request, int $projectId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('bank_guarantees')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $project = $this->findOwnedProject($projectId);

        $bankName = trim((string) $request->input('bank_name'));
        $amount = (float) $request->input('amount', 0);
        if ($bankName === '' || $amount <= 0) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', t('user.bank_guarantees.bank_and_amount_required'));
        }

        $data = [
            'company_id' => Auth::user()->company_id,
            'project_id' => $project->id,
            'type' => array_key_exists($request->input('type'), BankGuarantee::TYPES) ? $request->input('type') : 'other',
            'bank_name' => $bankName,
            'guarantee_number' => trim((string) $request->input('guarantee_number', '')),
            'amount' => $amount,
            'issue_date' => $request->input('issue_date') ?: null,
            'expiry_date' => $request->input('expiry_date') ?: null,
            'status' => 'active',
            'notes' => trim((string) $request->input('notes', '')),
        ];

        $uploadError = $this->handleUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/projects/' . $project->id, 'error', $uploadError);
        }

        BankGuarantee::create($data);
        return $this->redirectWithFlash('/app/projects/' . $project->id, 'success', t('user.bank_guarantees.added'));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('bank_guarantees')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $guarantee = $this->findOwned($id);

        $status = (string) $request->input('status');
        if (!array_key_exists($status, BankGuarantee::STATUSES)) {
            return redirect('/app/projects/' . $guarantee->project_id);
        }

        $guarantee->update(['status' => $status]);

        return $this->redirectWithFlash('/app/projects/' . $guarantee->project_id, 'success', t('user.bank_guarantees.status_changed', ['status' => $status]));
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('bank_guarantees')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $guarantee = $this->findOwned($id);
        $projectId = $guarantee->project_id;
        if ($guarantee->file_path) {
            $file = public_path($guarantee->file_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $guarantee->delete();
        return $this->redirectWithFlash('/app/projects/' . $projectId, 'success', t('user.bank_guarantees.removed'));
    }

    /** @param array $data by reference — sets file_path on success */
    private function handleUpload(Request $request, array &$data): ?string
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED_TYPES[$mime])) {
            return 'File must be a PDF, JPG, or PNG.';
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return 'File must be smaller than 10MB.';
        }
        $filename = 'bank-guarantee-' . Auth::user()->company_id . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_TYPES[$mime];
        $file->move(public_path('uploads/bank-guarantees'), $filename);
        $data['file_path'] = "/uploads/bank-guarantees/{$filename}";
        return null;
    }

    private function findOwned(int $id): BankGuarantee
    {
        $guarantee = BankGuarantee::find($id);
        abort_if(!$guarantee || $guarantee->company_id !== Auth::user()->company_id, 404, 'Bank guarantee not found.');
        return $guarantee;
    }

    private function findOwnedProject(int $id): Project
    {
        $project = Project::find($id);
        abort_if(!$project || $project->company_id !== Auth::user()->company_id, 404, 'Project not found.');
        return $project;
    }
}
