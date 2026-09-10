<?php

namespace App\Services;

/** Content-only rules shared by saved-template upgrades and SMS rendering. */
final class SmsTemplateContent
{
    public static function containsRomanUrdu(string $text): bool
    {
        $text = preg_replace('/\{[a-zA-Z]+\}/', '', $text);

        return preg_match('/\b(?:aap|baqi|tayyar|tashreef|shukriya|wajah|maafi|mil gay[ae]|hisaab saaf|date barhi)\b/iu', $text) === 1;
    }

    public static function variables(array $variables): array
    {
        foreach ($variables as $key => $value) {
            $variables[$key] = self::plain((string) ($value ?? ''));
        }
        foreach (['totalAmount', 'advancePaid', 'remainingBalance', 'paidAmount'] as $key) {
            if (! isset($variables[$key]) || $variables[$key] === '') continue;
            $value = $variables[$key];
            if (is_numeric($value)) $value = Money::format($value);
            $symbol = Settings::currency();
            if (str_starts_with($value, $symbol)) {
                $label = preg_match('/^(?:Rs\.?|PKR)$/i', $symbol) ? 'Rs' : trim($symbol);
                $value = $label.' '.ltrim(substr($value, strlen($symbol)));
            }
            $variables[$key] = $value;
        }

        return $variables;
    }

    public static function render(string $text, array $variables): string
    {
        $variables = self::variables($variables);

        // Optional clauses disappear instead of producing "Contact: ." or a
        // made-up explanation. This also keeps empty-shop previews readable.
        if (empty($variables['shopPhone'])) {
            $text = str_replace([' For assistance, call {shopPhone}.', ' Contact: {shopPhone}.'], '', $text);
        }
        if (empty($variables['reason'])) $text = str_replace(' Reason: {reason}.', '', $text);
        if (empty($variables['oldDate']) || ($variables['oldDate'] ?? null) === ($variables['newDate'] ?? null)) {
            $text = str_replace('from {oldDate} to {newDate}', 'to {newDate}', $text);
        }

        // One substitution pass: customer values cannot introduce template tokens.
        $text = preg_replace_callback('/\{([a-zA-Z]+)\}/', fn ($m) => $variables[$m[1]] ?? '', $text);
        $text = preg_replace('/\{[a-zA-Z]+\}/', '', $text);

        return self::plain($text);
    }

    private static function plain(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }
}
