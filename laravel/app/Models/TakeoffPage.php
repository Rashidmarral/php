<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sheet of a Takeoff's plan set — either the takeoff's single uploaded image (one row,
 * page_number=1) or one page of an uploaded multi-page PDF (one row per page, in order).
 * Calibration (scale_px_per_unit/scale_unit) is per-page, since different sheets of the same
 * plan set can be printed at different scales/zoom.
 */
class TakeoffPage extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['scale_px_per_unit' => 'decimal:6'];
    }

    public function takeoff(): BelongsTo
    {
        return $this->belongsTo(Takeoff::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(TakeoffMeasurement::class);
    }
}
