<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_approved_vendor' => 'boolean',
            'rating' => 'decimal:2',
            'exchange_rate_to_sar' => 'decimal:4',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    /** Pre-qualification documents (CR, insurance, classification certificate, etc.) — the supplier-level version of ComplianceDocument/TeamMemberDocument. */
    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class);
    }

    /** Full performance-rating history — never a single overwritable number, see averageRating(). */
    public function ratings(): HasMany
    {
        return $this->hasMany(SupplierRating::class);
    }

    /**
     * Live-computed average of every rating ever recorded for this supplier, or null if it
     * has none yet. Deliberately ignores the denormalized `rating` column (list-view display
     * only) — never trust a stale denormalized value when you can compute it, matching this
     * app's existing Subcontract::cumulativePaid() precedent.
     */
    public function averageRating(): ?float
    {
        $average = $this->ratings()->avg('score');
        return $average !== null ? round((float) $average, 1) : null;
    }
}
