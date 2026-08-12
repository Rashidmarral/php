<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitOfMeasure extends Model
{
    protected $table = 'units_of_measure';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
