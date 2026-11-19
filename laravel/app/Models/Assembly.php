<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable, company-defined group of estimate line items (e.g. "Standard
 * Interior Wall") expressed per one unit of base_uom (e.g. "lm" of wall).
 * See AssemblyItem for how an applied quantity scales into real line items.
 */
class Assembly extends Model
{
    public $timestamps = true;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(AssemblyItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
