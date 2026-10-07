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

    /**
     * Design decision: a subcontract does NOT get its own parallel rating system. A
     * Subcontract IS a Supplier hired under this specific project (see supplier() above), so
     * "rating the subcontractor's performance on this subcontract" is exactly the same thing
     * as "rating the Supplier, with project_id set to this subcontract's project_id" — the
     * identical SupplierRating row a company would create from the Supplier's own rating form.
     * This scopes the shared supplier_ratings table to (supplier_id, project_id) instead of
     * introducing a subcontract_ratings table that would just duplicate it.
     */
    public function ratings(): \Illuminate\Database\Eloquent\Builder
    {
        return SupplierRating::where('supplier_id', $this->supplier_id)->where('project_id', $this->project_id);
    }

    /** Live-computed average of this subcontract's own ratings (same rules as Supplier::averageRating()). */
    public function averageRating(): ?float
    {
        $average = $this->ratings()->avg('score');
        return $average !== null ? round((float) $average, 1) : null;
    }
}
