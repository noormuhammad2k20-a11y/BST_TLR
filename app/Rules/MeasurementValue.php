<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class MeasurementValue implements ValidationRule
{
    public function __construct(private readonly int $decimals)
    {
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) && !is_numeric($value)) {
            $fail('The :attribute format is invalid.');
            return;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        $parts = explode('-', $value);

        if (count($parts) > 2) {
            $fail('The :attribute may contain at most one hyphen.');
            return;
        }

        foreach ($parts as $part) {
            $part = trim($part);

            if (!is_numeric($part)) {
                $fail('The :attribute must be a number or a hyphenated pair of numbers.');
                return;
            }

            $floatValue = (float) $part;
            if ($floatValue < 0 || $floatValue > 999) {
                $fail('The :attribute numeric values must be between 0 and 999.');
                return;
            }

            if (str_contains($part, '.')) {
                $decimalsLength = strlen(explode('.', $part)[1]);
                if ($decimalsLength > $this->decimals) {
                    $fail('The :attribute allows at most ' . $this->decimals . ' decimal place(s).');
                    return;
                }
            }
        }
    }
}
