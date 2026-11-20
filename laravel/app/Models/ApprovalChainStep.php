<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ordered step of a company's configured multi-step approval chain for
 * one document_type ('estimate' or 'invoice') — see
 * Company::approvalChainFor(). A company with no rows for a given
 * document_type has no chain configured for it at all, and every
 * Estimate/Invoice of that type keeps the original single `approve_documents`
 * Gate check unchanged (see App\Support\ApprovalChain).
 */
class ApprovalChainStep extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    /** The only roles that ever had approval rights ('approve_documents' Gate originally granted owner/admin only) — estimator/viewer never did and are not valid chain steps. */
    public const ROLES = ['admin', 'accountant', 'owner'];

    public const DOCUMENT_TYPES = ['estimate', 'invoice'];

    /** Up to this many steps per company per document_type — enough for a real sign-off chain without turning into an unmanageable list. */
    public const MAX_STEPS = 4;

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The step's display name in the current locale: the company's own custom
     * label when it set one, else a role-derived default
     * ("Accountant approval" / "موافقة المحاسب") — see the
     * user.approvals.step_label_* translation keys.
     */
    public function displayLabel(): string
    {
        $custom = local($this, 'label');
        if ($custom !== '') {
            return $custom;
        }
        return t('user.approvals.step_label_' . $this->role_required);
    }
}
