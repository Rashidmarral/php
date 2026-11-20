<?php

namespace App\Support\Pdf;

use ArPHP\I18N\Arabic;

/**
 * dompdf does not perform Arabic glyph shaping/joining or bidi reordering on
 * its own, so Arabic text would render as disconnected, visually-reversed
 * letters. This pre-shapes text into presentation-form glyphs (still valid
 * UTF-8) before it reaches the PDF renderer.
 */
class ArabicText
{
    private static ?Arabic $engine = null;

    public static function shape(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }
        if (self::$engine === null) {
            self::$engine = new Arabic();
        }
        return self::$engine->utf8Glyphs($text, 2000);
    }
}
