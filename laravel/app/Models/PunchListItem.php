<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PunchListItem extends Model
{
    public const STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        // The punch-list-to-warranty-claim handoff: an item found during the project's
        // defects liability period (see Project::isInWarrantyPeriod()) rather than during
        // active construction. Reachable only via PunchListController::raiseWarrantyClaim(),
        // never from the ordinary status dropdown's normal open->in_progress->resolved flow.
        'warranty_claim' => 'Warranty Claim',
    ];

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'resolved_at' => 'datetime',
            'warranty_claim_raised_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
