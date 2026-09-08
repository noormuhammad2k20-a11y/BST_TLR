<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SmsLog;
use Illuminate\Support\Facades\Http;

class SmsService
{
    public static function enabled(): bool
    {
        return Settings::bool('sms_enabled');
    }

    public static function provider(): string
    {
        return Settings::str('sms_provider');
    }

    public static function providerLabel(): string
    {
        return self::provider() === 'veevo' ? 'Veevo Tech / SPEXT' : 'SendPK';
    }

    public static function configured(): bool
    {
        return match (self::provider()) {
            'veevo' => filled(Settings::str('veevo_api_key')),
            'sendpk' => filled(Settings::str('sendpk_api_key')) && filled(Settings::str('sendpk_sender_id')),
            default => false,
        };
    }

    public static function renderTemplate(string $id, array $variables): ?string
    {
        $template = Settings::activeSmsTemplate($id);

        return $template ? NotificationVariables::render($template['text'] ?? '', $variables) : null;
    }

    private static function request()
    {
        return Http::connectTimeout(config('messaging.connect_timeout'))->timeout(config('messaging.timeout'));
    }

    public static function send(?string $phone, string $message, ?int $orderId = null, ?int $customerId = null, ?string $templateId = null): array
    {
        $provider = 'sms';
        $log = false;
        $result = DeliveryResult::make($provider);
        try {
            $provider = self::provider();
            if (! self::enabled()) {
                return $result = DeliveryResult::make($provider, error: 'SMS delivery is switched off.');
            }
            $log = true;
            $phone = NotificationPhone::normalize($phone, $provider === 'veevo');
            if (! $phone) {
                return $result = DeliveryResult::make($provider, error: 'Enter a valid Pakistani mobile number.');
            }
            if (! self::configured()) {
                return $result = DeliveryResult::make($provider, error: 'Configure the selected SMS provider first.');
            }
            if (trim($message) === '') {
                return $result = DeliveryResult::make($provider, error: 'The SMS message is empty.');
            }
            $result = match ($provider) {
                'veevo' => self::veevo($phone, $message),
                'sendpk' => self::sendpk($phone, $message),
                default => DeliveryResult::make($provider, error: 'Unknown SMS provider.'),
            };
        } catch (\Throwable) {
            $result = DeliveryResult::make($provider, error: 'SMS request could not complete. Delivery may be unknown; check before resending.');
        } finally {
            if ($log) {
                DeliveryResult::log(SmsLog::class, ['phone' => mb_substr(DeliveryResult::safeText($phone), 0, 50), 'message' => DeliveryResult::safeText($message, 2000), 'template_id' => $templateId, 'order_id' => $orderId, 'customer_id' => $customerId], $result);
            }
        }

        return $result;
    }

    public static function sendTemplate(string $id, Order $order, array $extra = []): ?array
    {
        try {
            if (! self::enabled()) {
                return DeliveryResult::make(self::provider(), error: 'SMS delivery is switched off.');
            }
            $message = self::renderTemplate($id, NotificationVariables::variablesForOrder($order, $extra));

            return $message === null ? null : self::send($order->customer?->phone, $message, $order->id, $order->customer_id, $id);
        } catch (\Throwable) {
            return DeliveryResult::make('sms', error: 'SMS notification could not be prepared.');
        }
    }

    private static function veevo(string $phone, string $message): array
    {
        $payload = ['apikey' => Settings::str('veevo_api_key'), 'receivernum' => $phone, 'textmessage' => $message];
        if (filled(Settings::str('veevo_sender_id'))) {
            $payload['sendernum'] = Settings::str('veevo_sender_id');
        }
        $response = self::request()->post('https://api.veevotech.com/v3/sendsms', $payload);
        $data = $response->json();
        $data = is_array($data) ? $data : [];
        $ok = $response->successful() && ($data['STATUS'] ?? '') === 'SUCCESSFUL' && ! empty($data['MESSAGE_ID']);
        $metadata = ['http_status' => $response->status()];
        foreach (['STATUS', 'MESSAGE_ID', 'ERROR_CODE', 'ERROR_DESCRIPTION', 'NETWORK_NAME', 'RECEIVER_NUMBER', 'COUNTRY_CODE'] as $key) {
            if (isset($data[$key])) {
                $metadata[$key] = DeliveryResult::safeText($data[$key]);
            }
        }

        return DeliveryResult::make('veevo', $ok, $ok ? null : 'Veevo: '.DeliveryResult::safeText(($data['ERROR_DESCRIPTION'] ?? '') ?: 'SMS was not accepted. Check the provider error code and account credit.'),
            $ok ? DeliveryResult::safeText($data['MESSAGE_ID']) : null, $metadata);
    }

    private static function sendpk(string $phone, string $message): array
    {
        $payload = ['api_key' => Settings::str('sendpk_api_key'), 'sender' => Settings::str('sendpk_sender_id'),
            'mobile' => $phone, 'message' => $message, 'format' => 'plain'];
        if (preg_match('/[^\x00-\x7F]/', $message)) {
            $payload['type'] = 'unicode';
        }
        $response = self::request()->asForm()->post('https://sendpk.com/api/sms.php', $payload);
        $ok = $response->successful() && preg_match('/^OK\s+ID:([a-zA-Z0-9_-]+)\s*$/D', trim($response->body()), $matches);

        return DeliveryResult::make('sendpk', (bool) $ok, $ok ? null : self::sendpkError(trim($response->body())), $ok ? $matches[1] : null,
            ['http_status' => $response->status(), 'response' => DeliveryResult::safeText($response->body())]);
    }

    private static function sendpkError(string $code): string
    {
        return 'SendPK: '.match ($code) {
            '1' => 'API key invalid, expired, or account disabled.', '2' => 'API key is empty.',
            '4' => 'Sender ID is empty.', '5' => 'Recipient is empty.', '6' => 'Message is empty.',
            '7' => 'Invalid recipient number.', '8' => 'Insufficient credit.', '9' => 'SMS rejected.',
            default => 'The API did not return a recognized success response.',
        };
    }

    public static function testConnection(): array
    {
        try {
            if (! self::configured()) {
                return ['ok' => false, 'message' => 'Save the selected provider credentials first.'];
            }
            if (self::provider() === 'veevo') {
                return ['ok' => true, 'verified' => false, 'message' => 'Configuration present. Veevo credentials can only be checked by an explicit test SMS.'];
            }

            return self::balance();
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'SMS configuration could not be read.'];
        }
    }

    public static function balance(): array
    {
        try {
            if (self::provider() !== 'sendpk') {
                return ['ok' => false, 'message' => 'Balance checking is not available for this provider.'];
            }
            if (! filled(Settings::str('sendpk_api_key'))) {
                return ['ok' => false, 'message' => 'Save the SendPK API key first.'];
            }
            $response = self::request()->asForm()->post('https://sendpk.com/api/balance.php', ['api_key' => Settings::str('sendpk_api_key')]);
            $body = trim($response->body());
            // The documented plain response overloads numeric error codes and balance.
            // Never claim credentials verified for an ambiguous error-code value.
            if (! $response->successful() || in_array($body, ['1', '2', '3', '4', '5', '6', '7', '8', '9'], true) || ! preg_match('/^\d+(\.\d+)?$/D', $body)) {
                return ['ok' => false, 'message' => 'SendPK returned an error or ambiguous balance. Verify credit in the provider dashboard.'];
            }

            return ['ok' => true, 'verified' => true, 'balance' => $body, 'message' => 'SendPK balance: '.$body];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'SendPK balance request could not complete.'];
        }
    }
}
