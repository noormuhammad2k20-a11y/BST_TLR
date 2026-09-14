<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class StaffPayPeriod
{
    public static function current(string $frequency): string
    {
        return now()->format(match ($frequency) {
            'Daily' => 'Y-m-d', 'Weekly' => 'o-\\WW', default => 'Y-m',
        });
    }

    public static function bounds(string $period): array
    {
        try {
            if (preg_match('/^\d{4}-\d{2}$/D', $period)) {
                $start = CarbonImmutable::createFromFormat('!Y-m', $period);
                if ($start->format('Y-m') === $period) return [$start, $start->endOfMonth(), 'Monthly'];
            } elseif (preg_match('/^(\d{4})-W(\d{2})$/D', $period, $m)) {
                $start = CarbonImmutable::now()->setISODate((int) $m[1], (int) $m[2], 1)->startOfDay();
                if ($start->format('o-\\WW') === $period) return [$start, $start->addDays(6)->endOfDay(), 'Weekly'];
            } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $period)) {
                $start = CarbonImmutable::createFromFormat('!Y-m-d', $period);
                if ($start->format('Y-m-d') === $period) return [$start, $start->endOfDay(), 'Daily'];
            }
        } catch (\Throwable $e) {
            // Return the same validation response for malformed and impossible dates.
        }
        throw ValidationException::withMessages(['period' => 'Select a valid month, ISO week, or date.']);
    }
}
