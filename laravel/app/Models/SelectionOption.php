<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One priced choice a client can pick for a Selection (e.g. "Quartz" at 3,200 SAR). */
class SelectionOption extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_chosen' => 'boolean',
        ];
    }

    public function selection(): BelongsTo
    {
        return $this->belongsTo(Selection::class);
    }
}
