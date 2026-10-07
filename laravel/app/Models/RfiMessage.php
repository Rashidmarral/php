<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfiMessage extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class, 'rfi_id');
    }
}
