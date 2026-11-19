<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TakeoffMeasurement extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
            'gross_value' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'is_cutout' => 'boolean',
        ];
    }

    public function takeoff(): BelongsTo
    {
        return $this->belongsTo(Takeoff::class);
    }

    /** The specific plan sheet this measurement was drawn on. Nullable only for rows backfilled from before TakeoffPage existed that somehow found no page-1 row to attach to. */
    public function takeoffPage(): BelongsTo
    {
        return $this->belongsTo(TakeoffPage::class, 'takeoff_page_id');
    }

    /** The area measurement this one is cut out of. Only set when is_cutout is true. */
    public function parentMeasurement(): BelongsTo
    {
        return $this->belongsTo(TakeoffMeasurement::class, 'parent_measurement_id');
    }

    /** Cutouts carved out of this measurement's area — see TakeoffController's gross/net recompute logic. */
    public function cutouts(): HasMany
    {
        return $this->hasMany(TakeoffMeasurement::class, 'parent_measurement_id')->where('is_cutout', true);
    }

    /** The company's reusable item group this measurement expands into on conversion — see Assembly's docblock and TakeoffController::convertToEstimate(). Only ever set on a non-cutout length/area measurement. */
    public function assembly(): BelongsTo
    {
        return $this->belongsTo(Assembly::class);
    }
}
