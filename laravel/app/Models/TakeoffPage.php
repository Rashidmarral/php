<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sheet of a Takeoff's plan set — either the takeoff's single uploaded image (one row,
 * page_number=1) or one page of an uploaded multi-page PDF (one row per page, in order).
 * Calibration (scale_px_per_unit/scale_unit) is per-page, since different sheets of the same
 * plan set can be printed at different scales/zoom.
 *
 * version/supersedes_id/is_current give a sheet its own revision history — identical columns
 * and "never edit/delete a row, append a new one" shape as Document's own version tracking
 * (see TakeoffController::reviseUpload(), the exact mirror of DocumentController::uploadVersion()).
 * Uploading a revised plan image for this page creates a NEW takeoff_pages row with
 * supersedes_id pointing at this one and flips this row's is_current to false; this row's own
 * measurements (see measurements() below) are never touched or re-projected onto the new
 * row — they stay exactly where they were measured, against the drawing they were measured on.
 */
class TakeoffPage extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'scale_px_per_unit' => 'decimal:6',
            'is_current' => 'boolean',
        ];
    }

    public function takeoff(): BelongsTo
    {
        return $this->belongsTo(Takeoff::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(TakeoffMeasurement::class);
    }

    /** The previous version of this sheet this page replaces, if any. */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(TakeoffPage::class, 'supersedes_id');
    }

    /** The later page(s) that replaced this one — normally at most one. */
    public function supersededBy(): HasMany
    {
        return $this->hasMany(TakeoffPage::class, 'supersedes_id');
    }

    /**
     * The full version chain for this sheet, oldest first, ending with whichever row
     * is_current — i.e. every past and present revision of the same sheet, walked back
     * through supersedes_id. Never edits/deletes a row; each revision is its own immutable
     * takeoff_pages row (same precedent as Document::versionChain(), copied here adapted
     * to a takeoff page instead of a document).
     *
     * @return array<int, TakeoffPage>
     */
    public function versionChain(): array
    {
        $chain = [$this];

        $cursor = $this;
        while ($cursor->supersedes_id) {
            $cursor = TakeoffPage::find($cursor->supersedes_id);
            if (! $cursor) {
                break;
            }
            $chain[] = $cursor;
        }

        $cursor = $this;
        while (true) {
            $next = TakeoffPage::where('supersedes_id', $cursor->id)->first();
            if (! $next) {
                break;
            }
            $chain[] = $next;
            $cursor = $next;
        }

        $unique = [];
        foreach ($chain as $page) {
            $unique[$page->id] = $page;
        }
        ksort($unique);

        return array_values($unique);
    }
}
