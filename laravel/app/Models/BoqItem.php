<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoqItem extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Optional CSI-MasterFormat-style cost code — null (the default for every existing row) means "uncoded", see CostCode's docblock. */
    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class);
    }
}
