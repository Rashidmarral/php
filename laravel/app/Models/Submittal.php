<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Material/shop-drawing approval workflow: a submittal's history is the full sequence
 * of file revisions (see SubmittalRevision) plus its own status changes — a new
 * revision upload never resets the status history.
 */
class Submittal extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    public const STATUSES = [
        'submitted' => 'Submitted',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'approved_as_noted' => 'Approved as Noted',
        'rejected' => 'Rejected',
        'revise_resubmit' => 'Revise & Resubmit',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'reviewed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SubmittalRevision::class, 'submittal_id')->orderBy('revision_number');
    }

    public function latestRevision(): ?SubmittalRevision
    {
        return $this->revisions()->orderByDesc('revision_number')->first();
    }

    /** Display number, e.g. "SUB-001" — zero-padded to 3 digits, same formatting as Rfi::displayNumber(). */
    public function displayNumber(): string
    {
        return 'SUB-' . str_pad((string) $this->submittal_number, 3, '0', STR_PAD_LEFT);
    }
}
