<?php

namespace App\Services;

/**
 * Currency formatting that respects the currency symbol configured in Settings
 * instead of hardcoding one throughout the app.
 */
class Money
{
    public static function symbol(): string
    {
        return Settings::currency();
    }

    public static function format(float|int|string|null $amount, bool $decimals = false): string
    {
        $amount = (float) ($amount ?? 0);

        return self::symbol() . number_format($amount, $decimals ? 2 : 0);
    }

    /**
     * Compact form used on stat cards: 42000 -> "42K", 1250000 -> "12.5L".
     */
    public static function compact(float|int|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        $symbol = self::symbol();

        if (abs($amount) >= 10000000) {
            return $symbol . round($amount / 10000000, 1) . 'Cr';
        }

        if (abs($amount) >= 100000) {
            return $symbol . round($amount / 100000, 1) . 'L';
        }

        if (abs($amount) >= 1000) {
            return $symbol . round($amount / 1000, 1) . 'K';
        }

        return $symbol . number_format($amount);
    }
}
