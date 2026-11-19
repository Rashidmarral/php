<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TakeoffMeasurement extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
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
}
