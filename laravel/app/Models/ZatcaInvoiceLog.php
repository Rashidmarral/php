<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per ZATCA clearance/reporting attempt for an Invoice — the
 * historical submission record, independent of the single "current"
 * outcome BuildXact already tracks on invoices.zatca_* (see that
 * migration's docblock for why both exist side by side).
 *
 * Both timestamps are real here (unlike this codebase's usual
 * "created_at only" convention) — see the migration's docblock.
 */
class ZatcaInvoiceLog extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
