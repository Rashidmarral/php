<?php

namespace App\Support;

class Lang
{
    private static array $cache = [];

    /** Raw file-based translation defaults for a locale (resources/lang/{locale}.json), before any DB override is applied. */
    public static function fileDefaults(string $locale): array
    {
        if (!isset(self::$cache[$locale])) {
            $path = lang_path("{$locale}.json");
            self::$cache[$locale] = file_exists($path) ? (json_decode(file_get_contents($path), true) ?: []) : [];
        }
        return self::$cache[$locale];
    }
}
