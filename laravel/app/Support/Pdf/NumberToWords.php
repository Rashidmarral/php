<?php

namespace App\Support\Pdf;

/** Spells out a SAR amount in English words, e.g. 1482.00 -> "One Thousand Four Hundred Eighty Two Riyals Only". */
class NumberToWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    public static function sar(float $amount): string
    {
        $riyals = (int) floor($amount);
        $halalas = (int) round(($amount - $riyals) * 100);

        $words = self::spell($riyals) . ' Riyal' . ($riyals === 1 ? '' : 's');
        if ($halalas > 0) {
            $words .= ' and ' . self::spell($halalas) . ' Halala' . ($halalas === 1 ? '' : 's');
        }
        return $words . ' Only';
    }

    private static function spell(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }
        if ($number < 0) {
            return 'Minus ' . self::spell(-$number);
        }
        if ($number < 20) {
            return self::ONES[$number];
        }
        if ($number < 100) {
            return trim(self::TENS[intdiv($number, 10)] . ' ' . self::ONES[$number % 10]);
        }
        if ($number < 1000) {
            $remainder = $number % 100;
            return trim(self::ONES[intdiv($number, 100)] . ' Hundred' . ($remainder > 0 ? ' ' . self::spell($remainder) : ''));
        }
        if ($number < 1_000_000) {
            $remainder = $number % 1000;
            return trim(self::spell(intdiv($number, 1000)) . ' Thousand' . ($remainder > 0 ? ' ' . self::spell($remainder) : ''));
        }
        if ($number < 1_000_000_000) {
            $remainder = $number % 1_000_000;
            return trim(self::spell(intdiv($number, 1_000_000)) . ' Million' . ($remainder > 0 ? ' ' . self::spell($remainder) : ''));
        }
        $remainder = $number % 1_000_000_000;
        return trim(self::spell(intdiv($number, 1_000_000_000)) . ' Billion' . ($remainder > 0 ? ' ' . self::spell($remainder) : ''));
    }
}
