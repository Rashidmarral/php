<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Model;

class Translation extends Model
{
    protected static string $table = 'translations';

    /** @return array<string,string> map of translation_key => value for a locale */
    public static function overridesFor(string $locale): array
    {
        $rows = static::query('SELECT translation_key, value FROM translations WHERE locale = ?', [$locale])->fetchAll();
        $map = [];
        foreach ($rows as $row) {
            $map[$row['translation_key']] = $row['value'];
        }
        return $map;
    }

    public static function upsert(string $locale, string $key, string $value): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT id FROM translations WHERE locale = ? AND translation_key = ?');
        $stmt->execute([$locale, $key]);
        if ($row = $stmt->fetch()) {
            $pdo->prepare('UPDATE translations SET value = ? WHERE id = ?')->execute([$value, $row['id']]);
        } else {
            $pdo->prepare('INSERT INTO translations (locale, translation_key, value) VALUES (?, ?, ?)')->execute([$locale, $key, $value]);
        }
    }

    public static function deleteKey(string $key): void
    {
        static::query('DELETE FROM translations WHERE translation_key = ?', [$key]);
    }
}
