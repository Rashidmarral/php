<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Takeoff extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    /**
     * plan_image_path/scale_px_per_unit/scale_unit are vestigial: a takeoff's image(s) and
     * per-page calibration now live on TakeoffPage (see pages()). These three columns are
     * kept only so old data doesn't need a destructive migration, and are never written to
     * by current code — see the 2026_11_19_000100 migration's docblock.
     */
    protected function casts(): array
    {
        return ['scale_px_per_unit' => 'decimal:6'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** All measurements across every page of this takeoff (see TakeoffPage::measurements() for a single page's). */
    public function measurements(): HasMany
    {
        return $this->hasMany(TakeoffMeasurement::class);
    }

    /** This takeoff's plan sheets, in sheet order — one row for a single-image takeoff, one per sheet for an uploaded PDF. */
    public function pages(): HasMany
    {
        return $this->hasMany(TakeoffPage::class)->orderBy('page_number');
    }
}
