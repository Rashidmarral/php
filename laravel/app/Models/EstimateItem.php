<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total' => 'decimal:2',
            'is_optional' => 'boolean',
            'client_selected' => 'boolean',
        ];
    }

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    /** Optional CSI-MasterFormat-style cost code — null (the default for every existing row) means "uncoded", see CostCode's docblock. */
    public function costCode(): BelongsTo
    {
        return $this->belongsTo(CostCode::class);
    }
}
