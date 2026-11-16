<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Debit Note equivalent of ZatcaInvoiceLog — see that class and the
 * zatca_debit_note_logs migration for why this is a separate table/model
 * rather than a nullable widening of an existing log table.
 */
class ZatcaDebitNoteLog extends Model
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

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }
}
