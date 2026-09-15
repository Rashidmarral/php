<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subcontract extends Model
{
    public const STATUSES = ['active' => 'Active', 'completed' => 'Completed', 'terminated' => 'Terminated'];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contract_value' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubcontractPayment::class);
    }

    /** Cumulative value certified to date — the most recent certified payment's cumulative_value, or 0 if none. */
    public function cumulativePaid(): float
    {
        $latest = $this->payments()->where('status', 'certified')->orderByDesc('payment_number')->first();
        return (float) ($latest->cumulative_value ?? 0);
    }

    /** Total retention withheld across every certified payment on this subcontract. This app has no retention-release mechanism for subcontracts in this pass — see the module's scope note. */
    public function retentionHeld(): float
    {
        return (float) $this->payments()->where('status', 'certified')->sum('retention_amount');
    }

    /** True once this subcontract has any payment at all (draft or certified) — the point past which it can no longer be deleted. */
    public function hasAnyPayment(): bool
    {
        return $this->payments()->exists();
    }

    /** True once this subcontract has at least one CERTIFIED payment — the point past which contract_value/retention_percent can no longer be edited, since real money has already moved against them. */
    public function hasCertifiedPayment(): bool
    {
        return $this->payments()->where('status', 'certified')->exists();
    }
}
