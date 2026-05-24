<?php

namespace App\Services;

use App\Models\GlobalSetting;

class CurrencyHelper
{
    /**
     * Get the currently active currency.
     */
    public static function active(): string
    {
        return session('currency', 'USD');
    }

    /**
     * Get the exchange rate from USD to the target currency.
     */
    public static function rate(?string $currency = null): float
    {
        $currency = $currency ?? self::active();

        if ($currency === 'USD') {
            return 1.0;
        }

        if ($currency === 'EUR') {
            return (float) GlobalSetting::getValue('usd_to_eur_rate', 0.92);
        }

        if ($currency === 'TND') {
            return (float) GlobalSetting::getValue('usd_to_tnd_rate', 3.10);
        }

        return 1.0;
    }

    /**
     * Convert USD to the active currency.
     */
    public static function convert(float $usdAmount): float
    {
        return $usdAmount * self::rate();
    }

    /**
     * Convert active currency back to USD.
     */
    public static function convertBack(float $foreignAmount): float
    {
        $rate = self::rate();
        return $rate > 0 ? ($foreignAmount / $rate) : $foreignAmount;
    }

    /**
     * Get the currency symbol.
     */
    public static function symbol(?string $currency = null): string
    {
        $currency = $currency ?? self::active();

        return match ($currency) {
            'EUR' => '€',
            'TND' => ' TND',
            default => '$',
        };
    }

    /**
     * Format a USD price dynamically into the active currency with correct symbol and decimal count.
     */
    public static function format(float $usdAmount): string
    {
        $currency = self::active();
        $converted = self::convert($usdAmount);

        if ($currency === 'TND') {
            // Tunisian Dinars use 3 decimal places (millimes)
            return number_format($converted, 3, '.', ',') . ' TND';
        }

        if ($currency === 'EUR') {
            return '€' . number_format($converted, 2, '.', ',');
        }

        return '$' . number_format($converted, 2, '.', ',');
    }

    /**
     * Get Excel number format code for the active currency.
     */
    public static function excelFormatCode(bool $includeDecimals = false): string
    {
        $currency = self::active();

        if ($currency === 'TND') {
            return $includeDecimals ? '#,##0.000" TND"' : '#,##0" TND"';
        }

        if ($currency === 'EUR') {
            return $includeDecimals ? '"€"#,##0.00' : '"€"#,##0';
        }

        return $includeDecimals ? '"$"#,##0.00' : '"$"#,##0';
    }
}
