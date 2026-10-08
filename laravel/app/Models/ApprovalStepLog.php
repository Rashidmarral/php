<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step's progress on one specific Estimate/Invoice, snapshotted from the
 * company's approval_chain_steps the moment the document entered
 * approval_status='pending' — see App\Support\ApprovalChain::startIfChained().
 * Never created at all for a document whose company has no configured chain
 * for that document_type; that case keeps using only the flat
 * approval_status/approved_by/approved_at columns, exactly as before this
 * feature existed.
 */
class ApprovalStepLog extends Model
{
    /** Explicit because Eloquent's default pluralization of "ApprovalStepLog" would guess 'approval_step_logs', not this table's actual 'approval_steps_log' name. */
    protected $table = 'approval_steps_log';

    public $timestamps = true;

    protected $guarded = ['id'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'skipped'];

    protected function casts(): array
    {
        return [
            'step_order' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by');
    }
}
