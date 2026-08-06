<?php

namespace App\Core;

class Settings
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            $rows = Database::pdo()->query('SELECT key, value FROM settings')->fetchAll();
            foreach ($rows as $row) {
                self::$cache[$row['key']] = $row['value'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $all = self::load();
        return $all[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT key FROM settings WHERE key = ?');
        $exists->execute([$key]);
        if ($exists->fetch()) {
            $pdo->prepare('UPDATE settings SET value = ? WHERE key = ?')->execute([$value, $key]);
        } else {
            $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)')->execute([$key, $value]);
        }
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function all(): array
    {
        return self::load();
    }
}
