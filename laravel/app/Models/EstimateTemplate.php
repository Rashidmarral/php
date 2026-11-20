<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class EstimateTemplate extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default_choice' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(EstimateTemplateItem::class, 'template_id');
    }
}
