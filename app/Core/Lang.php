<?php

namespace App\Core {

class Lang
{
    private static array $strings = [];
    private static string $current = 'en';

    public static function boot(): void
    {
        $lang = $_SESSION['lang'] ?? 'en';
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'], true)) {
            $lang = $_GET['lang'];
            $_SESSION['lang'] = $lang;
        }
        self::$current = $lang;
        self::$strings = self::fileDefaults($lang);

        try {
            $overrides = \App\Models\Translation::overridesFor($lang);
            foreach ($overrides as $key => $value) {
                if ($value !== '') {
                    self::$strings[$key] = $value;
                }
            }
        } catch (\Throwable $e) {
            // Translations table may not exist yet on a not-yet-migrated install — file defaults still apply.
        }
    }

    /** Raw file-based default strings for a locale, unaffected by database overrides. */
    public static function fileDefaults(string $locale): array
    {
        $file = BASE_PATH . "/app/lang/{$locale}.php";
        return is_file($file) ? require $file : [];
    }

    public static function locale(): string
    {
        return self::$current;
    }

    public static function dir(): string
    {
        return self::$current === 'ar' ? 'rtl' : 'ltr';
    }

    public static function get(string $key, array $replace = []): string
    {
        $value = self::$strings[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $value = str_replace(':' . $k, $v, $value);
        }
        return $value;
    }
}

}

namespace {
    function t(string $key, array $replace = []): string
    {
        return \App\Core\Lang::get($key, $replace);
    }
}
