<?php

use App\Models\Translation;

if (!function_exists('t')) {
    /**
     * Translate a flat dotted key (e.g. 'common.save', 'admin.settings.trial_days').
     *
     * Looks up a DB-editable override for the current locale first (see the admin
     * Translations screen / Translation model) and falls back to the file-based
     * defaults in resources/lang/{locale}.json. Supports the same ':placeholder'
     * replacement syntax as Laravel's own __().
     */
    function t(string $key, array $replace = []): string
    {
        static $overridesByLocale = [];

        $locale = app()->getLocale();

        if (!array_key_exists($locale, $overridesByLocale)) {
            $overridesByLocale[$locale] = Translation::overridesFor($locale);
        }

        $line = $overridesByLocale[$locale][$key] ?? __($key);

        foreach ($replace as $placeholder => $value) {
            $line = str_replace(':' . $placeholder, (string) $value, $line);
        }

        return $line;
    }
}
