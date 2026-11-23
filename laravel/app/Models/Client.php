<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A client is a second, independent authenticatable identity (the client portal), distinct from company staff/admin `User` accounts. */
class Client extends Authenticatable
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'portal_enabled' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function estimates(): HasMany
    {
        return $this->hasMany(Estimate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
