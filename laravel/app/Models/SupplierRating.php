<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One 1-5 performance rating of a supplier/subcontractor, kept as history (never overwritten)
 * so Supplier::averageRating() can always recompute live instead of trusting a single stale
 * number. project_id is set whenever the rating is really "this subcontract's own rating" —
 * see Subcontract::ratings()/averageRating(), which reuses this same table rather than
 * building a parallel rating system for Subcontract.
 */
class SupplierRating extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_by');
    }
}
