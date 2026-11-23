<?php

namespace App\Support;

use App\Models\ApprovalChainStep;
use App\Models\ApprovalStepLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Task #54: the optional multi-step sequential approval chain layered on top
 * of Estimate/Invoice's original single-step approval_status/approved_by/
 * approved_at columns (see those models' own isApprovalBlocked() docblocks).
 *
 * The one rule every method here is built around: a document only has a
 * chain when approval_steps_log actually holds rows for it (hasChain()). A
 * company's live approval_chain_steps configuration is only ever consulted
 * once, at the moment a document first becomes approval_status='pending'
 * (startIfChained(), called from Estimate/Invoice's own `created` model
 * event) — after that, the snapshot in approval_steps_log is the single
 * source of truth for that document, so a company changing or removing its
 * chain configuration later never affects a document already mid-chain, and
 * a company that never configures a chain for a document_type never gets a
 * single row written here, ever. That emptiness is exactly what lets
 * EstimateController/InvoiceController's approve()/reject() fall straight
 * back to the original plain `approve_documents` Gate flow with zero
 * behavior change for the common case.
 */
class ApprovalChain
{
    /**
     * Snapshots the company's configured chain for $documentType onto a
     * freshly pending document, one approval_steps_log row per step (all
     * 'pending'). A no-op — on purpose — when the company has no chain
     * configured for this document_type, which is the common case and
     * leaves the document on the original single-step flow.
     */
    public static function startIfChained(Company $company, string $documentType, Model $document): void
    {
        $steps = $company->approvalChainFor($documentType);
        if ($steps->isEmpty()) {
            return;
        }

        $now = now();
        $rows = $steps->map(fn (ApprovalChainStep $step) => [
            'company_id' => $company->id,
            'document_type' => $documentType,
            'document_id' => $document->id,
            'step_order' => $step->step_order,
            'role_required' => $step->role_required,
            'status' => 'pending',
            'acted_by' => null,
            'acted_at' => null,
            'notes' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        ApprovalStepLog::insert($rows);
    }

    /** Whether THIS specific document is running a multi-step chain — the single switch every approve()/reject() dispatches on. */
    public static function hasChain(string $documentType, int $documentId): bool
    {
        return ApprovalStepLog::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->exists();
    }

    /** Every step row for this document, in order — for rendering full chain progress. */
    public static function allSteps(string $documentType, int $documentId): Collection
    {
        return ApprovalStepLog::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->orderBy('step_order')
            ->get();
    }

    /** The earliest still-pending step, i.e. the one awaiting action right now — or null once every step is resolved (or the document has no chain at all). */
    public static function currentStep(string $documentType, int $documentId): ?ApprovalStepLog
    {
        return ApprovalStepLog::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->orderBy('step_order')
            ->first();
    }

    /** True when $user's role matches the role required for the current pending step — the chain's replacement for the generic 'approve_documents' Gate check. */
    public static function canActOnCurrentStep(User $user, string $documentType, int $documentId): bool
    {
        $step = self::currentStep($documentType, $documentId);
        return $step !== null && $user->role === $step->role_required;
    }

    /**
     * Approves the current pending step. Returns 'done' once this was the
     * LAST pending step — the caller must then also set the parent
     * document's approval_status='approved' + approved_by/approved_at for
     * backward compatibility with every other reader of those columns.
     * Returns 'next' when another step is still pending — the caller must
     * leave the parent's approval_status at 'pending'.
     *
     * Assumes canActOnCurrentStep() was already checked; a no-op (returns
     * 'next') if there is no current pending step at all.
     */
    public static function approveCurrentStep(User $user, string $documentType, int $documentId): string
    {
        $step = self::currentStep($documentType, $documentId);
        if (!$step) {
            return 'next';
        }

        $step->update(['status' => 'approved', 'acted_by' => $user->id, 'acted_at' => now()]);

        $stillPending = ApprovalStepLog::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->exists();

        return $stillPending ? 'next' : 'done';
    }

    /**
     * Rejects the current pending step and marks every OTHER still-pending
     * step 'skipped' — a reject at any step kills the document immediately
     * (the caller sets the parent's approval_status='rejected' right after
     * this), so the remaining steps must not keep looking like they're
     * still awaiting action. Chosen over leaving them 'pending' because a
     * chain-progress UI reading raw step statuses would otherwise show a
     * rejected document as if steps after the rejection were still
     * actionable.
     */
    public static function rejectCurrentStep(User $user, string $documentType, int $documentId, ?string $reason): void
    {
        $step = self::currentStep($documentType, $documentId);
        if (!$step) {
            return;
        }

        $step->update(['status' => 'rejected', 'acted_by' => $user->id, 'acted_at' => now(), 'notes' => $reason]);

        ApprovalStepLog::where('document_type', $documentType)
            ->where('document_id', $documentId)
            ->where('status', 'pending')
            ->update(['status' => 'skipped']);
    }

    /**
     * The display label for one step_order, preferring the company's live
     * approval_chain_steps config (so an edited custom label shows up
     * immediately) and falling back to a role-derived default if that
     * config row is gone (e.g. the chain was reconfigured after this
     * document started).
     */
    public static function stepLabel(Company $company, string $documentType, int $stepOrder, string $roleRequired): string
    {
        $configStep = $company->approvalChainSteps()
            ->where('document_type', $documentType)
            ->where('step_order', $stepOrder)
            ->first();

        return $configStep ? $configStep->displayLabel() : t('user.approvals.step_label_' . $roleRequired);
    }

    /**
     * "Step 2 of 3: awaiting Accountant approval" style progress for the
     * document show page. Null when this document has no chain at all —
     * callers fall back to the original flat "awaiting approval" badge.
     *
     * Each entry in 'steps' is the raw ApprovalStepLog row plus its already-
     * resolved display 'label', so a view can render the whole list without
     * needing its own Company lookup per row.
     *
     * @return null|array{total:int, position:?int, currentLabel:?string, steps:Collection}
     */
    public static function progressFor(Company $company, string $documentType, int $documentId): ?array
    {
        $steps = self::allSteps($documentType, $documentId);
        if ($steps->isEmpty()) {
            return null;
        }

        $current = $steps->firstWhere('status', 'pending');
        $position = null;
        $currentLabel = null;

        $rows = $steps->map(function (ApprovalStepLog $step) use ($company, $documentType, &$current, &$position, &$currentLabel, $steps) {
            $label = self::stepLabel($company, $documentType, $step->step_order, $step->role_required);
            if ($current && $step->id === $current->id) {
                $position = $steps->search(fn (ApprovalStepLog $s) => $s->id === $current->id) + 1;
                $currentLabel = $label;
            }
            return (object) [
                'id' => $step->id,
                'step_order' => $step->step_order,
                'role_required' => $step->role_required,
                'status' => $step->status,
                'label' => $label,
            ];
        });

        return [
            'total' => $steps->count(),
            'position' => $position,
            'currentLabel' => $currentLabel,
            'steps' => $rows,
        ];
    }
}
