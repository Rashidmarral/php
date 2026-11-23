<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A budgeted "allowance" for a project item not yet finalized (e.g. "Kitchen
 * Countertop — 3,000 SAR allowance"), with a handful of priced SelectionOption rows
 * the client picks from in the portal (see PortalController::chooseOption()). Tracks
 * whether the client's actual pick came in over or under the allowance via variance().
 */
class Selection extends Model
{
    public const STATUSES = [
        'pending' => 'Pending',
        'client_selected' => 'Client Selected',
        'approved' => 'Approved',
        'ordered' => 'Ordered',
        'installed' => 'Installed',
    ];

    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'allowance_amount' => 'decimal:2',
            'due_date' => 'date:Y-m-d',
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

    public function options(): HasMany
    {
        return $this->hasMany(SelectionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The option the client has picked for this selection, or null if none has been chosen yet. */
    public function chosenOption(): ?SelectionOption
    {
        return $this->options()->where('is_chosen', true)->first();
    }

    /**
     * Chosen option's price minus the allowance — positive means the client's pick came
     * in OVER the allowance, negative means UNDER. Null until a choice is made, since
     * there's nothing to compare yet.
     */
    public function variance(): ?float
    {
        $chosen = $this->chosenOption();

        return $chosen ? round((float) $chosen->price - (float) $this->allowance_amount, 2) : null;
    }
}
