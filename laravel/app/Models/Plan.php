<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
        ];
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function features(): array
    {
        return json_decode((string) $this->attributes['features'], true) ?: [];
    }

    public function featureFlags(): array
    {
        return json_decode((string) $this->attributes['feature_flags'], true) ?: [];
    }
}
