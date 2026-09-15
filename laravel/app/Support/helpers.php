<?php

use App\Models\Translation;
use App\Support\Pdf\ArabicText;

if (!function_exists('money')) {
    /** Wrapped in <bdi> so mixed LTR numerals/currency don't get visually scrambled inside an RTL layout. */
    function money(float $amount, string $currency = 'SAR'): string
    {
        return '<bdi>' . number_format($amount, 2) . ' ' . e($currency) . '</bdi>';
    }
}

if (!function_exists('local')) {
    /** Picks the Arabic value of a field when browsing in Arabic and it's non-empty, else the English value. */
    function local(array|object $row, string $enKey, ?string $arKey = null): string
    {
        $row = is_object($row) && method_exists($row, 'toArray') ? $row->toArray() : (array) $row;
        $arKey = $arKey ?? $enKey . '_ar';
        if (app()->getLocale() === 'ar' && !empty($row[$arKey])) {
            return (string) $row[$arKey];
        }
        return (string) ($row[$enKey] ?? '');
    }
}

if (!function_exists('passwordToggle')) {
    function passwordToggle(): string
    {
        return <<<'HTML'
            <button type="button" class="password-toggle" data-visible="false" aria-label="Show password">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
            HTML;
    }
}

if (!function_exists('pdfText')) {
    /** Escapes text for a PDF template, reshaping Arabic glyphs when needed. */
    function pdfText(?string $value, string $lang = 'en'): string
    {
        $escaped = e($value);
        return $lang === 'ar' ? ArabicText::shape($escaped) : $escaped;
    }
}

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
