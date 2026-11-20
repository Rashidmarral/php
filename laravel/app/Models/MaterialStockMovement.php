<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialStockMovement extends Model
{
    /**
     * `qty` is always stored positive on every row — the row's effect on
     * Material::qty_on_hand is decided by `type` (and, for 'adjustment' only, by
     * `direction`), never by the sign of `qty` itself:
     *   - receive:              qty_on_hand += qty
     *   - issue:                qty_on_hand -= qty
     *   - adjustment, up:       qty_on_hand += qty
     *   - adjustment, down:     qty_on_hand -= qty
     */
    public const TYPES = [
        'receive' => 'Received',
        'issue' => 'Issued',
        'adjustment' => 'Adjustment',
    ];

    public const DIRECTIONS = [
        'up' => 'Up',
        'down' => 'Down',
    ];

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Signed effect this movement has on Material::qty_on_hand — positive increases it, negative decreases it. */
    public function delta(): float
    {
        $qty = (float) $this->qty;
        if ($this->type === 'receive') {
            return $qty;
        }
        if ($this->type === 'issue') {
            return -$qty;
        }
        // adjustment
        return $this->direction === 'down' ? -$qty : $qty;
    }
}
