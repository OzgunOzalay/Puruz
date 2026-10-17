<?php

namespace App\Support;

/**
 * Money is kept as integer kuruş everywhere; this class only converts at the edges.
 */
class Money
{
    public static function format(?int $kurus): string
    {
        $kurus ??= 0;
        $decimals = $kurus % 100 === 0 ? 0 : 2;

        return number_format($kurus / 100, $decimals, ',', '.').' ₺';
    }

    /** Decimal string in the format iyzico expects, e.g. 1250.5 for 125050 kuruş. */
    public static function toDecimal(int $kurus): string
    {
        $value = number_format($kurus / 100, 2, '.', '');

        return rtrim(rtrim($value, '0'), '.') ?: '0';
    }

    /** Accepts "1250", "1.250,50", "1250.50" or numbers and returns kuruş. */
    public static function parse(string|int|float|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (int) round($value * 100);
        }

        $value = str_replace([' ', '₺', 'TL'], '', $value);
        if (str_contains($value, ',')) {
            // Turkish style: 1.250,50
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            // Thousands separators only: 2.000 means two thousand, not two.
            $value = str_replace('.', '', $value);
        }

        return (int) round(((float) $value) * 100);
    }
}
