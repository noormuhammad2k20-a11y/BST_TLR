<?php

namespace App\Services;

/** Only explicitly selected provider metadata may cross the logging/UI boundary. */
final class DeliveryResult
{
    public static function make(string $provider, bool $sent = false, ?string $error = null, ?string $id = null, array $metadata = []): array
    {
        return ['sent' => $sent, 'provider' => $provider, 'status' => $sent ? 'accepted' : 'failed',
            'error' => $error, 'message_id' => $id, 'metadata' => $metadata];
    }

    public static function safeText(mixed $text, int $limit = 500): string
    {
        $text = is_scalar($text) ? (string) $text : '';
        foreach (['veevo_api_key', 'sendpk_api_key'] as $key) {
            try {
                $secret = Settings::str($key);
                if ($secret !== '') {
                    $text = str_replace([$secret, rawurlencode($secret)], '[REDACTED]', $text);
                }
            } catch (\Throwable) {
                return 'Provider request failed.';
            }
        }

        return mb_substr($text, 0, $limit);
    }

    public static function log(string $model, array $context, array $result): void
    {
        try {
            $model::create(array_merge($context, [
                'provider' => $result['provider'], 'status' => $result['status'],
                'provider_message_id' => $result['message_id'], 'error' => $result['error'],
                'api_response' => json_encode($result['metadata']),
                'sent_at' => $result['sent'] ? now() : null,
            ]));
        } catch (\Throwable) {
            try {
                logger()->warning('Notification log could not be written.', ['provider' => $result['provider']]);
            } catch (\Throwable) { /* A logging failure cannot affect a business transaction. */
            }
        }
    }
}
