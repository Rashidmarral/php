<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebitNoteItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(DebitNote::class);
    }
}
