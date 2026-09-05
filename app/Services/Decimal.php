<?php

namespace App\Services;

/** Fixed-point business arithmetic. Floats are deliberately rejected. */
final class Decimal
{
    public static function value(string|int|null $value): string
    {
        $value = (string) ($value ?? '0');
        if (!preg_match('/^-?\d+(?:\.\d+)?$/D', $value)) {
            throw new \InvalidArgumentException('Invalid decimal value.');
        }
        return bcadd($value, bccomp($value, '0', 8) < 0 ? '-0.005' : '0.005', 2);
    }

    public static function add(string|int $a, string|int $b): string { return bcadd((string) $a, (string) $b, 2); }
    public static function sub(string|int $a, string|int $b): string { return bcsub((string) $a, (string) $b, 2); }
    public static function mul(string|int $a, string|int $b): string { return self::value(bcmul((string) $a, (string) $b, 8)); }
    public static function ratio(string $a, string $b, string $c): string
    {
        if (self::cmp($c, '0') === 0) return '0.00';
        return self::value(bcdiv(bcmul($a, $b, 12), $c, 8));
    }
    public static function cmp(string|int $a, string|int $b): int { return bccomp((string) $a, (string) $b, 2); }
    public static function min(string $a, string $b): string { return self::cmp($a, $b) <= 0 ? $a : $b; }
    public static function max(string $a, string $b = '0.00'): string { return self::cmp($a, $b) >= 0 ? $a : $b; }
}
