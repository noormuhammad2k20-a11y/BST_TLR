<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Provider-based SMS delivery, architecturally mirroring WhatsAppService.
 *
 * Reuses the existing template variable system (WhatsAppService::variablesForOrder,
 * WhatsAppService::render) instead of duplicating it. SMS templates are stored
 * separately because they are shorter than WhatsApp messages.
 *
 * Adding a second SMS provider later is one new private method + one settings
 * key — no rewriting needed.
 */
class SmsService
{
    /* ------------------------------------------------------------------ */
    /*  Configuration                                                      */
    /* ------------------------------------------------------------------ */

    public static function enabled(): bool
    {
        return Settings::bool('sms_enabled');
    }

    public static function provider(): string
    {
        $provider = Settings::str('sms_provider');

        return in_array($provider, ['sendpk'], true) ? $provider : 'sendpk';
    }

    public static function providerLabel(): string
    {
        return match (self::provider()) {
            'sendpk' => 'SendPK',
            default  => 'SMS',
        };
    }

    /**
     * Whether the currently selected SMS provider has valid credentials.
     */
    public static function configured(): bool
    {
        return match (self::provider()) {
            'sendpk' => filled(self::sendPkCredentials()['api_key']),
            default  => false,
        };
    }

    /**
     * @return array{api_key: string, sender_id: string, sms_type: string}
     */
    public static function sendPkCredentials(): array
    {
        return [
            'api_key'   => trim(Settings::str('sendpk_api_key')),
            'sender_id' => trim(Settings::str('sendpk_sender_id')),
            'sms_type'  => Settings::str('sendpk_sms_type') ?: 'semi_branded',
        ];
    }

    /** Digits only, normalised for SMS APIs. */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return blank($digits) ? null : $digits;
    }

    /* ------------------------------------------------------------------ */
    /*  Template rendering (reuses WhatsAppService)                        */
    /* ------------------------------------------------------------------ */

    /**
     * Renders a saved SMS template by id. Returns null when the shop has
     * switched that template off, so callers can skip sending entirely.
     *
     * @param array<string, string> $variables
     */
    public static function renderTemplate(string $templateId, array $variables): ?string
    {
        $template = Settings::activeSmsTemplate($templateId);

        return $template ? WhatsAppService::render($template['text'] ?? '', $variables) : null;
    }

    /* ------------------------------------------------------------------ */
    /*  Delivery                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Delivers one SMS message using the configured provider.
     *
     * Always returns a structured result rather than throwing, because a
     * messaging failure must never roll back the order or payment that
     * triggered it.
     *
     * @return array{sent: bool, provider: string, message: string, error: ?string}
     */
    public static function send(?string $phone, string $message, ?int $orderId = null, ?int $customerId = null, ?string $templateId = null): array
    {
        $result = [
            'sent'     => false,
            'provider' => self::provider(),
            'message'  => $message,
            'error'    => null,
        ];

        if (!self::enabled()) {
            $result['error'] = 'SMS delivery is switched off in Settings.';

            return $result;
        }

        $phone = self::normalisePhone($phone);

        if (!$phone) {
            $result['error'] = 'This customer has no usable phone number.';

            return $result;
        }

        if (!self::configured()) {
            $result['error'] = 'SMS provider credentials are not configured in Settings.';

            return $result;
        }

        $api = match (self::provider()) {
            'sendpk' => self::sendPkCall($phone, $message),
            default  => ['ok' => false, 'error' => 'Unknown SMS provider.', 'body' => ''],
        };

        if ($api['ok']) {
            $result['sent'] = true;

            SmsLog::record([
                'phone'        => $phone,
                'message'      => $message,
                'template_id'  => $templateId,
                'provider'     => self::provider(),
                'status'       => 'sent',
                'order_id'     => $orderId,
                'customer_id'  => $customerId,
                'api_response' => is_string($api['body']) ? $api['body'] : json_encode($api['body']),
            ]);

            return $result;
        }

        $result['error'] = $api['error'];

        SmsLog::record([
            'phone'        => $phone,
            'message'      => $message,
            'template_id'  => $templateId,
            'provider'     => self::provider(),
            'status'       => 'failed',
            'error'        => $api['error'],
            'order_id'     => $orderId,
            'customer_id'  => $customerId,
            'api_response' => is_string($api['body']) ? $api['body'] : json_encode($api['body']),
        ]);

        return $result;
    }

    /**
     * Sends the SMS template registered for an order event, if it is switched on.
     *
     * @param array<string, string> $extra
     * @return array{sent: bool, provider: string, message: string, error: ?string}|null
     */
    public static function sendTemplate(string $templateId, Order $order, array $extra = []): ?array
    {
        $message = self::renderTemplate($templateId, WhatsAppService::variablesForOrder($order, $extra));

        if ($message === null) {
            return null;
        }

        return self::send(
            $order->customer?->phone,
            $message,
            $order->id,
            $order->customer_id,
            $templateId
        );
    }

    /* ------------------------------------------------------------------ */
    /*  SendPK provider                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * One HTTP call to the SendPK API.
     *
     * @return array{ok: bool, body: string, error: ?string}
     */
    private static function sendPkCall(string $phone, string $message): array
    {
        $creds = self::sendPkCredentials();

        if (!filled($creds['api_key'])) {
            return ['ok' => false, 'body' => '', 'error' => 'SendPK API key is missing in Settings.'];
        }

        try {
            $response = Http::timeout(15)->get('https://sendpk.com/api/sms.api.php', [
                'api_key'   => $creds['api_key'],
                'sender'    => $creds['sender_id'],
                'mobile'    => $phone,
                'message'   => $message,
                'format'    => 'json',
            ]);

            $body = $response->body();

            if (!$response->successful()) {
                return [
                    'ok'    => false,
                    'body'  => $body,
                    'error' => 'SendPK returned HTTP ' . $response->status() . '.',
                ];
            }

            // SendPK returns various success indicators in its response.
            // A successful send typically contains "ok" or a message ID.
            $decoded = $response->json();
            $bodyLower = strtolower($body);

            // Check for explicit error messages
            if (is_array($decoded) && isset($decoded['error'])) {
                return [
                    'ok'    => false,
                    'body'  => $body,
                    'error' => 'SendPK: ' . (is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error'])),
                ];
            }

            // If response contains known error strings
            if (str_contains($bodyLower, 'invalid') || str_contains($bodyLower, 'error') || str_contains($bodyLower, 'fail')) {
                // But not if it's actually a success response containing 'message_id'
                if (!str_contains($bodyLower, 'message_id') && !str_contains($bodyLower, 'ok')) {
                    return [
                        'ok'    => false,
                        'body'  => $body,
                        'error' => 'SendPK rejected the request: ' . substr($body, 0, 200),
                    ];
                }
            }

            return ['ok' => true, 'body' => $body, 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('SendPK SMS request failed', ['phone' => $phone, 'error' => $e->getMessage()]);

            return [
                'ok'    => false,
                'body'  => '',
                'error' => 'Could not reach SendPK: ' . $e->getMessage(),
            ];
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Diagnostics                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Confirms the SendPK API key is valid without sending anything.
     *
     * @return array{ok: bool, status: ?string, error: ?string}
     */
    public static function testConnection(): array
    {
        if (!self::configured()) {
            return ['ok' => false, 'status' => null, 'error' => 'Enter your SendPK API key first.'];
        }

        // Use the balance endpoint as a connectivity/credential check.
        $balance = self::balance();

        if ($balance['ok']) {
            return [
                'ok'     => true,
                'status' => 'Connected — ' . ($balance['data']['balance'] ?? 'Balance available'),
                'error'  => null,
            ];
        }

        return ['ok' => false, 'status' => null, 'error' => $balance['error']];
    }

    /**
     * Fetches SMS balance / package information from SendPK.
     *
     * @return array{ok: bool, data: array, error: ?string}
     */
    public static function balance(): array
    {
        $creds = self::sendPkCredentials();

        if (!filled($creds['api_key'])) {
            return ['ok' => false, 'data' => [], 'error' => 'SendPK API key is missing.'];
        }

        try {
            $response = Http::timeout(10)->get('https://sendpk.com/api/sms.api.php', [
                'api_key' => $creds['api_key'],
                'action'  => 'balance',
                'format'  => 'json',
            ]);

            if (!$response->successful()) {
                return [
                    'ok'    => false,
                    'data'  => [],
                    'error' => 'SendPK returned HTTP ' . $response->status(),
                ];
            }

            $body = $response->body();
            $decoded = $response->json();

            // Parse the balance response
            if (is_array($decoded)) {
                return [
                    'ok'   => true,
                    'data' => [
                        'balance' => $decoded['balance'] ?? $decoded['remaining_sms'] ?? $body,
                        'raw'     => $decoded,
                    ],
                    'error' => null,
                ];
            }

            return [
                'ok'   => true,
                'data' => ['balance' => trim($body), 'raw' => $body],
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('SendPK balance check failed', ['error' => $e->getMessage()]);

            return [
                'ok'    => false,
                'data'  => [],
                'error' => 'Could not reach SendPK: ' . $e->getMessage(),
            ];
        }
    }
}
