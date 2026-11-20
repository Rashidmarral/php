<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project-level hours-worked record for one team member on one day. hourly_rate_snapshot
 * and cost are computed once at creation time (TimesheetController::store()) and never
 * re-derived later, same precedent as SubcontractPayment::retention_percent — so a later
 * change to the member's User::hourly_rate never silently rewrites an already-logged
 * entry's cost.
 */
class TimesheetEntry extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'work_date' => 'date:Y-m-d',
            'hours' => 'decimal:2',
            'hourly_rate_snapshot' => 'decimal:2',
            'cost' => 'decimal:2',
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

    /** The team member who worked the hours — may differ from logged_by. */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function loggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by');
    }
}
