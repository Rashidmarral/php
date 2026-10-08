<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A standalone HSE (Health, Safety, Environment) incident record — a near miss, first
 * aid case, lost time injury, or property damage logged against a project. Unlike
 * Rfi/Submittal this is not a threaded conversation: it's a simple record with a
 * status lifecycle, the same shape as PunchListItem (see PunchListController).
 */
class SafetyIncident extends Model
{
    public const SEVERITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    public const STATUSES = ['open' => 'Open', 'under_investigation' => 'Under Investigation', 'closed' => 'Closed'];

    /** Suggested options for the incident_type dropdown — free text with an "Other" escape hatch, not a rigid enum. */
    public const SUGGESTED_TYPES = ['Near Miss', 'First Aid', 'Lost Time Injury', 'Property Damage'];

    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'incident_date' => 'date:Y-m-d',
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

    public function reportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** Display number, e.g. "INC-001" — zero-padded to 3 digits, same formatting as Rfi/Submittal. */
    public function displayNumber(): string
    {
        return 'INC-' . str_pad((string) $this->incident_number, 3, '0', STR_PAD_LEFT);
    }
}
