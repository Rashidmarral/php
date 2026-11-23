<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line item's price within a supplier's RfqQuote — optional, used only when that
 * supplier's quote was itemized (see RfqController::recordQuote()/ShareController::
 * submitRfqBid()). Always deleted and recreated wholesale on resubmission, never
 * edited in place, mirroring AsphaltEstimateSegment's own precedent — hence no
 * updated_at.
 */
class RfqQuoteItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(RfqQuote::class, 'rfq_quote_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class, 'rfq_item_id');
    }
}
