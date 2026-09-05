<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Turns the persisted message templates into real messages and delivers them
 * by whichever method the shop configured.
 *
 * "manual" hands back a wa.me deep link for the user to send themselves;
 * "ultramsg" posts to the UltraMsg API. Either way the message body comes from
 * the templates saved in Settings, never from a string baked into a controller.
 */
class WhatsAppService
{
    /* ------------------------------------------------------------------ */
    /*  Rendering                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Every placeholder value for one order, drawn from live records.
     *
     * @return array<string, string>
     */
    public static function variablesForOrder(Order $order, array $extra = []): array
    {
        $order->loadMissing('customer');

        $due = Dates::format($order->delivery_date, 'To be confirmed');

        // The most recent payment, which is what "paidAmount" means to a
        // customer reading a receipt. Falls back to the advance.
        $lastPayment = $order->relationLoaded('payments')
            ? (float) ($order->payments->sortByDesc('date')->first()?->amount ?? 0)
            : (float) ($order->payments()->latest('date')->value('amount') ?? 0);

        return array_merge([
            'customerName'     => $order->customer?->name ?? 'Customer',
            'customerPhone'    => $order->customer?->phone ?? '',
            'customerID'       => $order->customer?->display_code ?? '',
            'orderID'          => $order->display_number,
            'invoiceID'        => $order->display_invoice,
            'garmentType'      => $order->primary_item_name,
            'fabric'           => $order->fabric ?? '',
            'quantity'         => (string) ($order->items[0]['qty'] ?? 1),
            'dueDate'          => $due,
            'dueTime'          => $order->time_slot ?? '',
            'totalAmount'      => Money::format($order->total),
            'advancePaid'      => Money::format($order->advance),
            'remainingBalance' => Money::format($order->balance_due),
            'paidAmount'       => Money::format($lastPayment ?: $order->advance),
            'status'           => $order->status,

            // Only meaningful when a date is actually being changed, but given
            // sensible values here so no template ever renders a blank hole.
            'newDate'          => $due,
            'oldDate'          => $due,
            'reason'           => 'Schedule change',
        ], self::shopVariables(), $extra);
    }

    /**
     * @return array<string, string>
     */
    public static function variablesForCustomer(Customer $customer, array $extra = []): array
    {
        return array_merge([
            'customerName'  => $customer->name,
            'customerPhone' => $customer->phone ?? '',
            'customerID'    => $customer->display_code,
        ], self::shopVariables(), $extra);
    }

    /**
     * @return array<string, string>
     */
    public static function shopVariables(): array
    {
        return [
            'shopName'    => Settings::str('store_name') ?: 'Atelier',
            'shopPhone'   => Settings::str('whatsapp_number') ?: Settings::str('phone'),
            'shopAddress' => Settings::str('address'),
            'todayDate'   => Dates::format(now()),
        ];
    }

    /**
     * Substitutes {placeholders} in a template body. Unknown placeholders are
     * stripped rather than left visible in the customer's message.
     *
     * @param array<string, string> $variables
     */
    public static function render(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{' . $key . '}', (string) $value, $text);
        }

        return trim(preg_replace('/\{[a-zA-Z]+\}/', '', $text));
    }

    /**
     * Renders a saved template by id. Returns null when the shop has switched
     * that template off, so callers can skip sending entirely.
     *
     * @param array<string, string> $variables
     */
    public static function renderTemplate(string $templateId, array $variables): ?string
    {
        $template = Settings::activeTemplate($templateId);

        return $template ? self::render($template['text'] ?? '', $variables) : null;
    }

    /* ------------------------------------------------------------------ */
    /*  Delivery                                                           */
    /* ------------------------------------------------------------------ */

    public static function enabled(): bool
    {
        return Settings::bool('whatsapp_enabled');
    }

    public static function provider(): string
    {
        $provider = Settings::str('whatsapp_provider');

        return in_array($provider, ['gateway', 'ultramsg'], true) ? $provider : 'manual';
    }

    /** Human label for the configured provider, used in messages back to the UI. */
    public static function providerLabel(): string
    {
        return match (self::provider()) {
            'gateway'  => 'the free gateway',
            'ultramsg' => 'UltraMsg',
            default    => 'WhatsApp Web',
        };
    }

    /** Digits only, as both wa.me and UltraMsg expect. */
    public static function normalisePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return blank($digits) ? null : $digits;
    }

    /** A wa.me deep link with the message already filled in. */
    public static function manualLink(?string $phone, string $message): ?string
    {
        $phone = self::normalisePhone($phone);

        return $phone ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($message) : null;
    }

    /**
     * Delivers one message using the configured provider.
     *
     * Always returns a structured result rather than throwing, because a
     * messaging failure must never roll back the order or payment that
     * triggered it.
     *
     * @return array{sent: bool, provider: string, url: ?string, message: string, error: ?string, fell_back: bool}
     */
    public static function send(?string $phone, string $message): array
    {
        $result = [
            'sent'      => false,
            'provider'  => self::provider(),
            'url'       => null,
            'message'   => $message,
            'error'     => null,
            'fell_back' => false,
        ];

        if (!self::enabled()) {
            $result['error'] = 'WhatsApp delivery is switched off in Settings.';

            return $result;
        }

        $phone = self::normalisePhone($phone);

        if (!$phone) {
            $result['error'] = 'This customer has no usable phone number.';

            return $result;
        }

        // Manual mode never contacts an API: the user sends from WhatsApp Web.
        if (self::provider() === 'manual') {
            $result['url']  = self::manualLink($phone, $message);
            $result['sent'] = true;

            return $result;
        }

        $api = self::provider() === 'gateway'
            ? self::gatewayCall('send', ['phone' => $phone, 'message' => $message])
            : self::ultramsgCall('messages/chat', ['to' => $phone, 'body' => $message]);

        if ($api['ok']) {
            $result['sent'] = true;

            return $result;
        }

        $result['error'] = $api['error'];

        // Hybrid behaviour: if the automatic route is down, hand back a manual
        // link so the shop can still send by hand. A message is never lost
        // just because the gateway happens to be closed.
        if (self::fallbackEnabled()) {
            $result['url']        = self::manualLink($phone, $message);
            $result['fell_back']  = true;
        }

        return $result;
    }

    /** Whether a failed automatic send should degrade to a manual link. */
    public static function fallbackEnabled(): bool
    {
        return self::provider() === 'ultramsg'
            || Settings::bool('gateway_fallback_manual');
    }

    /**
     * Sends the template registered for an order event, if it is switched on.
     *
     * @param array<string, string> $extra
     * @return array{sent: bool, provider: string, url: ?string, message: string, error: ?string}|null
     */
    public static function sendTemplate(string $templateId, Order $order, array $extra = []): ?array
    {
        $message = self::renderTemplate($templateId, self::variablesForOrder($order, $extra));

        if ($message === null) {
            return null;
        }

        return self::send($order->customer?->phone, $message);
    }

    /* ------------------------------------------------------------------ */
    /*  Self-hosted gateway                                                */
    /* ------------------------------------------------------------------ */

    public static function gatewayUrl(): string
    {
        return rtrim(Settings::str('gateway_url') ?: 'http://localhost:3001', '/');
    }

    public static function gatewayConfigured(): bool
    {
        return filled(Settings::str('gateway_url')) && filled(Settings::str('gateway_token'));
    }

    /**
     * Live gateway state: whether it is running, linked to a phone, and the
     * pairing QR when it is waiting to be scanned.
     *
     * @return array{running: bool, connected: bool, phone: ?string, qr: ?string, queued: int, error: ?string}
     */
    public static function gatewayStatus(): array
    {
        $offline = [
            'running' => false, 'connected' => false, 'connecting' => false, 'phone' => null,
            'qr' => null, 'queued' => 0, 'sent' => 0, 'failed' => 0,
            'reconnects' => 0, 'uptime' => 0, 'linked_for' => 0, 'error' => null,
        ];

        if (!self::gatewayConfigured()) {
            // array_merge, not `+`: the union operator keeps the left-hand
            // side's null `error` and silently swallows this message — which is
            // the one message a shop that has not filled the boxes in needs.
            return array_merge($offline, ['error' => 'Enter the gateway address and token first.']);
        }

        $api = self::gatewayCall('status', [], 'GET');

        if (!$api['ok']) {
            return array_merge($offline, ['error' => $api['error']]);
        }

        $body = $api['body'];

        return [
            'running'    => true,
            'connected'  => (bool) ($body['connected'] ?? false),
            'connecting' => (bool) ($body['connecting'] ?? false),
            'phone'      => $body['phone'] ?? null,
            'qr'         => $body['qr'] ?? null,
            'queued'     => (int) ($body['queued'] ?? 0),
            'sent'       => (int) ($body['sent'] ?? 0),
            // The gateway has always reported these; nothing was reading them,
            // so a shop had no way to tell a healthy link from one that keeps
            // dropping and re-pairing itself.
            'failed'     => (int) ($body['failed'] ?? 0),
            'reconnects' => (int) ($body['reconnects'] ?? 0),
            'uptime'     => (int) ($body['uptimeSeconds'] ?? 0),
            'linked_for' => (int) ($body['linkedSeconds'] ?? 0),
            'error'      => $body['error'] ?? null,
        ];
    }

    /** Unlinks the phone so a different number can be paired. */
    public static function gatewayLogout(): array
    {
        return self::gatewayCall('logout');
    }

    /**
     * One HTTP call to the local gateway.
     *
     * A short timeout is deliberate: the gateway is normally on localhost, and
     * a slow reply almost always means it is not running. Failing fast lets the
     * manual fallback kick in while the user is still looking at the screen.
     *
     * @return array{ok: bool, body: array, error: ?string}
     */
    private static function gatewayCall(string $endpoint, array $payload = [], string $method = 'POST'): array
    {
        if (!self::gatewayConfigured()) {
            return ['ok' => false, 'body' => [], 'error' => 'The gateway address or token is missing in Settings.'];
        }

        $url = self::gatewayUrl() . '/' . ltrim($endpoint, '/');

        try {
            $request = Http::timeout($method === 'GET' ? 5 : 20)
                ->acceptJson()
                ->withHeaders(['X-Gateway-Token' => Settings::str('gateway_token')]);

            $response = $method === 'GET'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);
        } catch (\Throwable $e) {
            Log::info('WhatsApp gateway unreachable', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return [
                'ok'    => false,
                'body'  => [],
                'error' => 'The gateway is not running. Start start-gateway.bat, or send this one by hand.',
            ];
        }

        $body = $response->json() ?? [];

        if ($response->status() === 401) {
            return ['ok' => false, 'body' => $body, 'error' => 'The gateway rejected the token. Check it matches config.json.'];
        }

        if (!$response->successful() || !($body['ok'] ?? false)) {
            return [
                'ok'    => false,
                'body'  => $body,
                'error' => $body['error'] ?? ('The gateway returned HTTP ' . $response->status() . '.'),
            ];
        }

        return ['ok' => true, 'body' => $body, 'error' => null];
    }

    /* ------------------------------------------------------------------ */
    /*  UltraMsg                                                           */
    /* ------------------------------------------------------------------ */

    public static function credentials(): array
    {
        return [
            'instance' => trim(Settings::str('ultramsg_instance')),
            'token'    => trim(Settings::str('ultramsg_token')),
        ];
    }

    /** Whether the *currently selected* provider has what it needs to send. */
    public static function configured(): bool
    {
        return match (self::provider()) {
            'gateway'  => self::gatewayConfigured(),
            'ultramsg' => filled(self::credentials()['instance']) && filled(self::credentials()['token']),
            default    => true, // manual needs nothing
        };
    }

    /**
     * Confirms the configured provider is reachable and ready, without sending
     * anything to a customer. Powers the Test Connection button in Settings.
     *
     * @return array{ok: bool, status: ?string, error: ?string}
     */
    public static function testConnection(): array
    {
        if (self::provider() === 'manual') {
            return ['ok' => true, 'status' => 'manual', 'error' => null];
        }

        if (self::provider() === 'gateway') {
            $status = self::gatewayStatus();

            return match (true) {
                !$status['running']   => ['ok' => false, 'status' => null, 'error' => $status['error'] ?? 'The gateway is not running.'],
                $status['connected']  => ['ok' => true, 'status' => 'linked to ' . ($status['phone'] ?? 'your phone'), 'error' => null],
                (bool) $status['qr']  => ['ok' => false, 'status' => 'waiting', 'error' => 'The gateway is running but not linked yet. Scan the QR code below.'],
                default               => ['ok' => false, 'status' => null, 'error' => $status['error'] ?? 'The gateway is starting up. Try again in a few seconds.'],
            };
        }

        if (!filled(self::credentials()['instance']) || !filled(self::credentials()['token'])) {
            return ['ok' => false, 'status' => null, 'error' => 'Enter an UltraMsg Instance ID and API token first.'];
        }

        $api = self::ultramsgCall('instance/status', [], 'GET');

        if (!$api['ok']) {
            return ['ok' => false, 'status' => null, 'error' => $api['error']];
        }

        // UltraMsg reports the linked handset under status.accountStatus.
        $status = data_get($api['body'], 'status.accountStatus.status')
            ?? data_get($api['body'], 'status.accountStatus')
            ?? 'connected';

        $status = is_array($status) ? json_encode($status) : (string) $status;

        if (str_contains(strtolower($status), 'authenticated') || strtolower($status) === 'connected') {
            return ['ok' => true, 'status' => $status, 'error' => null];
        }

        return ['ok' => false, 'status' => $status, 'error' => 'UltraMsg reported the instance as "' . $status . '". Scan the QR code in your UltraMsg dashboard.'];
    }

    /**
     * One HTTP call to the UltraMsg instance, with every failure mode folded
     * into a predictable shape.
     *
     * @return array{ok: bool, body: array, error: ?string}
     */
    private static function ultramsgCall(string $endpoint, array $payload = [], string $method = 'POST'): array
    {
        $c = self::credentials();

        if (!filled($c['instance']) || !filled($c['token'])) {
            return ['ok' => false, 'body' => [], 'error' => 'UltraMsg is selected but its credentials are missing in Settings.'];
        }

        $url = sprintf('https://api.ultramsg.com/%s/%s', rawurlencode($c['instance']), $endpoint);

        try {
            $request = Http::timeout(15)->acceptJson();

            $response = $method === 'GET'
                ? $request->get($url, array_merge(['token' => $c['token']], $payload))
                : $request->asForm()->post($url, array_merge(['token' => $c['token']], $payload));
        } catch (\Throwable $e) {
            Log::warning('UltraMsg request failed', ['endpoint' => $endpoint, 'error' => $e->getMessage()]);

            return ['ok' => false, 'body' => [], 'error' => 'Could not reach UltraMsg: ' . $e->getMessage()];
        }

        $body = $response->json() ?? [];

        if (!$response->successful()) {
            return ['ok' => false, 'body' => $body, 'error' => 'UltraMsg returned HTTP ' . $response->status() . '.'];
        }

        // UltraMsg signals rejection in the payload even on a 200.
        if (isset($body['error']) && $body['error']) {
            $error = is_array($body['error']) ? json_encode($body['error']) : (string) $body['error'];

            return ['ok' => false, 'body' => $body, 'error' => 'UltraMsg rejected the request: ' . $error];
        }

        return ['ok' => true, 'body' => $body, 'error' => null];
    }
}
