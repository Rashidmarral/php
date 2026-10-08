<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One supplier's bid against an Rfq's line items — compared side by side with the RFQ's other quotes. */
class RfqQuote extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'is_awarded' => 'boolean',
            'submitted_at' => 'date:Y-m-d',
        ];
    }

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
