<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A single marker pinned to a point on a Document's drawing/plan image — placed by
 * clicking the canvas in documents.viewer (the exact canvasPoint() pixel-coordinate
 * mechanic takeoffs/show.blade.php already uses for its measurement tools, adapted
 * for a single click) and attached to either a PunchListItem or an Rfi via the
 * standard pinnable_type/pinnable_id polymorphic pair.
 *
 * x/y are stored as a PERCENTAGE (0-100) of the image's natural width/height, not raw
 * pixels — see the create_document_pins_table migration's comment for why.
 */
class DocumentPin extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The PunchListItem or Rfi this pin is attached to. */
    public function pinnable(): MorphTo
    {
        return $this->morphTo();
    }
}
