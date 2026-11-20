<?php

namespace App\Models;

use App\Core\Model;

class PasswordReset extends Model
{
    protected static string $table = 'password_resets';

    public static function findValid(string $token): ?array
    {
        $row = static::query(
            'SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at > ? LIMIT 1',
            [$token, date('Y-m-d H:i:s')]
        )->fetch();
        return $row ?: null;
    }
}
