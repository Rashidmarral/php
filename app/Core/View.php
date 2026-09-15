<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/site'): void
    {
        extract($data);

        $viewFile = BASE_PATH . "/app/Views/{$view}.php";
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo "View not found: {$view}";
            return;
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout === null) {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . "/app/Views/{$layout}.php";
        require $layoutFile;
    }

    public static function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    /** Wrapped in <bdi> so mixed LTR numerals/currency don't get visually scrambled inside an RTL layout. */
    public static function money(float $amount, string $currency = 'SAR'): string
    {
        return '<bdi>' . number_format($amount, 2) . ' ' . self::e($currency) . '</bdi>';
    }

    public static function old(string $key, string $default = ''): string
    {
        return self::e($_SESSION['old'][$key] ?? $default);
    }

    /** Picks the Arabic value of a bilingual field when browsing in Arabic and one was entered, English otherwise. */
    public static function local(array $row, string $enKey, ?string $arKey = null): string
    {
        $arKey = $arKey ?? $enKey . '_ar';
        if (Lang::locale() === 'ar' && !empty($row[$arKey])) {
            return (string) $row[$arKey];
        }
        return (string) ($row[$enKey] ?? '');
    }

    /** Show/hide eye button for a password field — pair with wrapping the <input> in <div class="password-field">. */
    public static function passwordToggle(): string
    {
        return <<<HTML
            <button type="button" class="password-toggle" data-visible="false" aria-label="Show password">
              <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-5.94M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
            HTML;
    }

    /** Escapes text for a PDF template, reshaping Arabic glyphs when needed. */
    public static function pdfText(?string $value, string $lang = 'en'): string
    {
        $escaped = self::e($value);
        return $lang === 'ar' ? ArabicText::shape($escaped) : $escaped;
    }
}
