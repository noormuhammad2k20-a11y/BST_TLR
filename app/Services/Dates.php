<?php

namespace App\Services;

use Carbon\CarbonInterface;

/**
 * Date rendering that respects the format and timezone chosen in Settings, so
 * every date in the app reads the same way and follows the shop's clock.
 */
class Dates
{
    /** A date in the shop's configured display format. */
    public static function format(CarbonInterface|string|null $date, ?string $fallback = '—'): string
    {
        $date = self::parse($date);

        return $date ? $date->format(Settings::phpDateFormat()) : (string) $fallback;
    }

    /** Date plus a 12-hour time, for receipts and timestamps. */
    public static function formatWithTime(CarbonInterface|string|null $date, ?string $fallback = '—'): string
    {
        $date = self::parse($date);

        return $date ? $date->format(Settings::phpDateFormat() . ', g:i A') : (string) $fallback;
    }

    /** Relative wording ("3 days ago"), localised to the chosen language. */
    public static function forHumans(CarbonInterface|string|null $date, ?string $fallback = '—'): string
    {
        $date = self::parse($date);

        return $date ? $date->diffForHumans() : (string) $fallback;
    }

    /** Parses any accepted input into the shop's timezone. */
    public static function parse(CarbonInterface|string|null $date): ?CarbonInterface
    {
        if (blank($date)) {
            return null;
        }

        try {
            $carbon = $date instanceof CarbonInterface ? $date->copy() : \Illuminate\Support\Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }

        return $carbon->setTimezone(Settings::timezone());
    }
}
