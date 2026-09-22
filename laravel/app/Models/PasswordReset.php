<?php

namespace App\Models;


class PasswordReset extends Model
{
    protected $table = 'password_resets';

    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public static function findValid(string $token): ?self
    {
        return static::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
