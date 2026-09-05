<?php
namespace App\Services;

use Illuminate\Support\Facades\Crypt;

final class SecretSettings
{
    public static function isSecret(string $key): bool
    {
        return !empty(Settings::SCHEMA[$key]['secret']) || preg_match('/(?:token|password|secret|api_key)$/i',$key)===1;
    }
    public static function encode(string $key, mixed $value): mixed
    {
        if (!self::isSecret($key) || $value===null || $value==='') return $value;
        // Always encrypt submitted plaintext; callers must never submit stored ciphertext.
        return 'enc:v1:'.Crypt::encryptString((string)$value);
    }
    public static function decode(string $key,mixed $value): mixed
    {
        if (self::isSecret($key) && is_string($value) && str_starts_with($value,'enc:v1:')) return Crypt::decryptString(substr($value,7));
        return $value;
    }
    public static function redact(array $values): array
    {
        foreach ($values as $key=>&$value) {
            if (is_string($key) && self::isSecret($key)) $value='[REDACTED]';
            elseif (is_array($value)) $value=self::redact($value);
        }
        return $values;
    }
}
