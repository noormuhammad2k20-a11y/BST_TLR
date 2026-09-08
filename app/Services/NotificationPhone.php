<?php

namespace App\Services;

final class NotificationPhone
{
    public static function normalize(?string $phone, bool $plus = false): ?string
    {
        $phone = trim((string) $phone);
        if ($phone === '' || ! preg_match('/^\+?[0-9\s()\-]+$/D', $phone)) {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        if (preg_match('/^03\d{9}$/D', $digits)) {
            $digits = '92'.substr($digits, 1);
        } elseif (preg_match('/^3\d{9}$/D', $digits)) {
            $digits = '92'.$digits;
        }
        if (! preg_match('/^923\d{9}$/D', $digits)) {
            return null;
        }

        return ($plus ? '+' : '').$digits;
    }
}
