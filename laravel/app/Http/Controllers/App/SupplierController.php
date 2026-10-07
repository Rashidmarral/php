<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Models\SupplierRating;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SupplierController extends Controller
{
    private const ALLOWED_DOCUMENT_TYPES = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /** Mirrors SettingsController/legal.blade's Grade 1-5 contractor_classification convention, applied here to a supplier instead of the company itself. */
    public const CLASSIFICATION_GRADES = ['1', '2', '3', '4', '5'];

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        $query = Supplier::where('company_id', Auth::user()->company_id);
        $approvedOnly = $request->query('approved_only') === '1';
        if ($approvedOnly) {
            $query->where('is_approved_vendor', true);
        }
        $suppliers = $query->orderBy('name')->get();

        $rows = $suppliers->map(fn (Supplier $s) => [
            ...$s->toArray(),
            'average_rating' => $s->averageRating(),
        ])->all();

        return view('app.suppliers.index', [
            'suppliers' => $rows,
            'approvedOnly' => $approvedOnly,
        ]);
    }

    public function create(): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        return view('app.suppliers.form', ['supplier' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/suppliers/create', 'error', t('user.suppliers.name_required'));
        }
        Supplier::create([
            'company_id' => Auth::user()->company_id,
            'name' => $name,
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'contact_name' => $request->input('contact_name', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            'category' => $request->input('category', ''),
            'notes' => $request->input('notes', ''),
            ...$this->qualificationFields($request),
        ]);
        $this->flash('success', t('user.suppliers.added'));
        return redirect('/app/suppliers');
    }

    public function show(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);

        $documents = SupplierDocument::where('supplier_id', $supplier->id)
            ->orderByRaw('expiry_date IS NULL')
            ->orderBy('expiry_date')
            ->get()
            ->toArray();

        $ratings = SupplierRating::where('supplier_id', $supplier->id)->orderByDesc('created_at')->get();
        $raters = User::whereIn('id', $ratings->pluck('rated_by')->filter()->unique()->all())->get()->keyBy('id');
        $projects = Project::where('company_id', $supplier->company_id)->orderBy('name')->get();
        $projectNames = $projects->keyBy('id');

        $ratingRows = $ratings->map(fn (SupplierRating $r) => [
            ...$r->toArray(),
            'rater_name' => $raters->get($r->rated_by)->name ?? '—',
            'project_name' => $r->project_id ? ($projectNames->get($r->project_id)->name ?? '—') : null,
        ])->all();

        return view('app.suppliers.show', [
            'supplier' => $supplier->toArray(),
            'documents' => $documents,
            'ratings' => $ratingRows,
            'averageRating' => $supplier->averageRating(),
            'projects' => $projects->toArray(),
            'classificationGrades' => self::CLASSIFICATION_GRADES,
        ]);
    }

    public function edit(int $id): View|RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        return view('app.suppliers.form', [
            'supplier' => $this->findOwned($id)->toArray(),
            'classificationGrades' => self::CLASSIFICATION_GRADES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);
        $supplier->update([
            'name' => trim((string) $request->input('name')),
            'name_ar' => trim((string) $request->input('name_ar', '')),
            'contact_name' => $request->input('contact_name', ''),
            'email' => $request->input('email', ''),
            'phone' => $request->input('phone', ''),
            'address' => $request->input('address', ''),
            'category' => $request->input('category', ''),
            'notes' => $request->input('notes', ''),
            ...$this->qualificationFields($request),
        ]);
        $this->flash('success', t('user.suppliers.updated'));
        return redirect('/app/suppliers');
    }

    public function destroy(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $this->findOwned($id)->delete();
        $this->flash('success', t('user.suppliers.removed'));
        return redirect('/app/suppliers');
    }

    /** Quick one-click flip of is_approved_vendor, separate from the full edit form (which also lets you set the notes alongside it). */
    public function toggleApprovedVendor(int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);
        $supplier->update(['is_approved_vendor' => !$supplier->is_approved_vendor]);
        $this->flash('success', $supplier->is_approved_vendor ? t('user.suppliers.marked_approved') : t('user.suppliers.marked_unapproved'));
        return redirect('/app/suppliers/' . $supplier->id);
    }

    public function storeDocument(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWithFlash('/app/suppliers/' . $supplier->id, 'error', t('user.suppliers.document_name_required'));
        }

        $data = [
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'doc_type' => trim((string) $request->input('doc_type', '')) ?: null,
            'name' => $name,
            'document_number' => trim((string) $request->input('document_number', '')) ?: null,
            'expiry_date' => $request->input('expiry_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];

        $uploadError = $this->handleDocumentUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/suppliers/' . $supplier->id, 'error', $uploadError);
        }

        SupplierDocument::create($data);
        $this->flash('success', t('user.suppliers.document_added'));
        return redirect('/app/suppliers/' . $supplier->id);
    }

    public function updateDocument(Request $request, int $id, int $docId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);
        $doc = $this->findOwnedDocument($docId, $supplier->id);

        $data = [
            'doc_type' => trim((string) $request->input('doc_type', '')) ?: null,
            'name' => trim((string) $request->input('name')),
            'document_number' => trim((string) $request->input('document_number', '')) ?: null,
            'expiry_date' => $request->input('expiry_date') ?: null,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ];

        // Renewing the expiry date clears the "expiring soon" reminder flag so a fresh
        // reminder can fire again ahead of the new date — same convention as ComplianceController.
        $currentExpiry = $doc->expiry_date?->format('Y-m-d');
        if (($data['expiry_date'] ?? null) !== $currentExpiry) {
            $data['reminder_sent_at'] = null;
        }

        $uploadError = $this->handleDocumentUpload($request, $data);
        if ($uploadError) {
            return $this->redirectWithFlash('/app/suppliers/' . $supplier->id, 'error', $uploadError);
        }

        $doc->update($data);
        $this->flash('success', t('user.suppliers.document_updated'));
        return redirect('/app/suppliers/' . $supplier->id);
    }

    public function destroyDocument(int $id, int $docId): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);
        $this->findOwnedDocument($docId, $supplier->id)->delete();
        $this->flash('success', t('user.suppliers.document_removed'));
        return redirect('/app/suppliers/' . $supplier->id);
    }

    public function storeRating(Request $request, int $id): RedirectResponse
    {
        if ($redirect = $this->requireFeature('suppliers')) {
            return $redirect;
        }
        if ($redirect = $this->requireAbility('write')) {
            return $redirect;
        }
        $supplier = $this->findOwned($id);

        $score = (int) $request->input('score');
        if ($score < 1 || $score > 5) {
            return $this->redirectWithFlash('/app/suppliers/' . $supplier->id, 'error', t('user.suppliers.rating_score_required'));
        }

        // When the rating is submitted from a Subcontract's own page, its project_id is taken
        // from the subcontract itself (never trusted raw from the request) — this is what makes
        // "rate this subcontract" and "rate this supplier for project X" the same write, per
        // Subcontract::ratings()'s design note.
        $redirectPath = '/app/suppliers/' . $supplier->id;
        $projectId = null;
        $subcontractId = $request->input('subcontract_id') ? (int) $request->input('subcontract_id') : null;
        if ($subcontractId) {
            $subcontract = Subcontract::find($subcontractId);
            if ($subcontract && $subcontract->company_id === $supplier->company_id && $subcontract->supplier_id === $supplier->id) {
                $projectId = $subcontract->project_id;
                $redirectPath = '/app/subcontracts/' . $subcontract->id;
            }
        } else {
            $projectId = $this->ownedProjectId($request->input('project_id') ?: null, $supplier->company_id);
        }

        SupplierRating::create([
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'project_id' => $projectId,
            'rated_by' => Auth::id(),
            'score' => $score,
            'notes' => trim((string) $request->input('notes', '')) ?: null,
        ]);

        // Keep the denormalized list-view column roughly in sync — purely cosmetic, never
        // read back as the real average (see Supplier::averageRating()).
        $supplier->update(['rating' => $supplier->averageRating()]);

        $this->flash('success', t('user.suppliers.rating_added'));
        return redirect($redirectPath);
    }

    /** @param array $data by reference — sets file_path on success */
    private function handleDocumentUpload(Request $request, array &$data): ?string
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return null;
        }
        $mime = $file->getMimeType();
        if (!isset(self::ALLOWED_DOCUMENT_TYPES[$mime])) {
            return t('user.suppliers.document_file_type_error');
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            return t('user.suppliers.document_file_size_error');
        }
        $filename = 'supplier-doc-' . Auth::user()->company_id . '-' . bin2hex(random_bytes(6)) . '.' . self::ALLOWED_DOCUMENT_TYPES[$mime];
        $file->move(public_path('uploads/supplier-documents'), $filename);
        $data['file_path'] = "/uploads/supplier-documents/{$filename}";
        return null;
    }

    /** Shared by store()/update(): the pre-qualification fields, normalized and validated the same way for both. */
    private function qualificationFields(Request $request): array
    {
        $grade = $request->input('classification_grade');
        return [
            'trade_category' => trim((string) $request->input('trade_category', '')) ?: null,
            'cr_number' => trim((string) $request->input('cr_number', '')) ?: null,
            'vat_number' => trim((string) $request->input('vat_number', '')) ?: null,
            'classification_grade' => in_array($grade, self::CLASSIFICATION_GRADES, true) ? $grade : null,
            'is_approved_vendor' => $request->boolean('is_approved_vendor'),
            'approved_vendor_notes' => trim((string) $request->input('approved_vendor_notes', '')) ?: null,
        ];
    }

    /** Only returns the project id if it belongs to $companyId — never trust a raw project_id from the request. */
    private function ownedProjectId(?int $id, int $companyId): ?int
    {
        if (!$id) {
            return null;
        }
        $project = Project::find($id);
        return ($project && $project->company_id === $companyId) ? $project->id : null;
    }

    private function findOwned(int $id): Supplier
    {
        $supplier = Supplier::find($id);
        abort_if(!$supplier || $supplier->company_id !== Auth::user()->company_id, 404, 'Supplier not found.');
        return $supplier;
    }

    private function findOwnedDocument(int $id, int $supplierId): SupplierDocument
    {
        $doc = SupplierDocument::find($id);
        abort_if(!$doc || $doc->supplier_id !== $supplierId, 404, 'Document not found.');
        return $doc;
    }
}
