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

    /**
     * $token is the raw value from the reset-link URL; the column only ever
     * stores its SHA-256 digest (see AuthController::sendResetLink()), so a
     * leaked copy of this table — a backup, a replication lag, a SQL
     * injection — can't be used to reset anyone's password directly.
     */
    public static function findValid(string $token): ?self
    {
        return static::where('token', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }
}
