<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    public static function enabled(): bool
    {
        return Settings::bool('whatsapp_enabled');
    }

    public static function provider(): string
    {
        return 'meta';
    }

    public static function providerLabel(): string
    {
        return 'Meta WhatsApp Cloud API';
    }

    public static function configured(): bool
    {
        return filled(Settings::str('meta_access_token')) && filled(Settings::str('meta_phone_number_id')) && filled(Settings::str('meta_waba_id'));
    }

    public static function normalisePhone(?string $phone): ?string
    {
        return NotificationPhone::normalize($phone);
    }

    public static function mappings(): array
    {
        $saved = collect(Settings::json('meta_templates'))->keyBy('id');

        return array_map(fn ($t) => array_merge(['id' => $t['id'], 'active' => false, 'name' => '', 'language' => 'en_US', 'parameters' => []], $saved->get($t['id'], [])), Settings::defaultTemplates());
    }

    private static function request()
    {
        return Http::withToken(Settings::str('meta_access_token'))->acceptJson()
            ->connectTimeout(config('messaging.connect_timeout'))->timeout(config('messaging.timeout'));
    }

    private static function url(string $path): string
    {
        return 'https://graph.facebook.com/'.config('messaging.meta_version').'/'.$path;
    }

    private static function error($response): string
    {
        return 'Meta: '.DeliveryResult::safeText($response->json('error.message') ?: 'The API request failed (HTTP '.$response->status().').');
    }

    /** Cursor pagination stays on the official host; never follow an API-supplied URL with credentials. */
    private static function templates(): array
    {
        $all = [];
        $after = null;
        for ($page = 0; $page < 100; $page++) {
            $params = ['fields' => 'name,language,status,components,parameter_format', 'limit' => 100];
            if ($after) {
                $params['after'] = $after;
            }
            $response = self::request()->get(self::url(Settings::str('meta_waba_id').'/message_templates'), $params);
            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw new \RuntimeException(self::error($response));
            }
            $all = array_merge($all, $response->json('data'));
            if (! $response->json('paging.next')) {
                return $all;
            }
            $next = $response->json('paging.cursors.after');
            if (! $next || $next === $after) {
                throw new \RuntimeException('Meta template pagination could not be completed.');
            }
            $after = $next;
        }
        throw new \RuntimeException('Too many templates. Template verification could not be completed.');
    }

    public static function validateMapping(array $mapping, array $templates): ?string
    {
        if (empty($mapping['name']) || empty($mapping['language'])) {
            return 'Choose an approved template and language.';
        }
        $template = collect($templates)->first(fn ($t) => ($t['name'] ?? '') === $mapping['name'] && ($t['language'] ?? '') === $mapping['language']);
        if (! $template || ($template['status'] ?? '') !== 'APPROVED') {
            return 'The selected template/language is not approved in this WABA.';
        }
        if (($template['parameter_format'] ?? 'POSITIONAL') !== 'POSITIONAL') {
            return 'Use a positional text-body template ({{1}}, {{2}}, …).';
        }
        $body = null;
        foreach ($template['components'] ?? [] as $component) {
            if (($component['type'] ?? '') === 'BODY') {
                $body = $component['text'] ?? '';
            } elseif (($component['type'] ?? '') !== 'FOOTER') {
                return 'This integration supports text-body templates with an optional static footer. Remove header/buttons in Meta or select another template.';
            }
        }
        if ($body === null) {
            return 'The Meta template has no text body.';
        }
        preg_match_all('/\{\{(\d+)\}\}/', $body, $matches);
        $positions = array_values(array_unique(array_map('intval', $matches[1])));
        sort($positions);
        $parameters = $mapping['parameters'] ?? [];
        if ($positions !== (count($parameters) ? range(1, count($parameters)) : [])) {
            return 'The ordered variable count does not match the approved body parameters.';
        }
        foreach ($parameters as $key) {
            if (! array_key_exists($key, Settings::templateVariables())) {
                return 'Unknown Tailor template variable.';
            }
        }

        return null;
    }

    public static function testConnection(): array
    {
        try {
            if (! self::configured()) {
                return ['ok' => false, 'message' => 'Save the Meta access token, Phone Number ID and WABA ID first.'];
            }
            $response = self::request()->get(self::url(Settings::str('meta_phone_number_id')), ['fields' => 'id,display_phone_number,verified_name']);
            if (! $response->successful() || $response->json('id') !== Settings::str('meta_phone_number_id')) {
                return ['ok' => false, 'message' => self::error($response)];
            }
            $templates = self::templates();
            $mappings = array_map(fn ($m) => ['id' => $m['id'], 'active' => (bool) $m['active'],
                'error' => self::validateMapping($m, $templates)], self::mappings());

            return ['ok' => true, 'message' => 'Meta credentials verified. Review each template status before sending.',
                'sender' => ['phone' => DeliveryResult::safeText($response->json('display_phone_number')), 'name' => DeliveryResult::safeText($response->json('verified_name'))], 'templates' => $mappings];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => DeliveryResult::safeText($e->getMessage())];
        } catch (\Throwable) {
            return ['ok' => false, 'message' => 'Meta validation could not complete. Check configuration and network access.'];
        }
    }

    public static function sendTemplate(string $id, Order $order, array $extra = []): ?array
    {
        try {
            return self::sendMapped($id, $order->customer?->phone, NotificationVariables::variablesForOrder($order, $extra), $order->id, $order->customer_id);
        } catch (\Throwable) {
            return DeliveryResult::make('meta', error: 'WhatsApp notification could not be prepared.');
        }
    }

    public static function sendMapped(string $id, ?string $phone, array $variables, ?int $orderId = null, ?int $customerId = null): array
    {
        $result = DeliveryResult::make('meta');
        $mapping = null;
        $log = false;
        try {
            if (! self::enabled()) {
                return $result = DeliveryResult::make('meta', error: 'WhatsApp delivery is switched off.');
            }
            $log = true;
            $mapping = collect(self::mappings())->firstWhere('id', $id);
            if (! $mapping || ! $mapping['active']) {
                return $result = DeliveryResult::make('meta', error: 'This Meta event mapping is disabled.');
            }
            $phone = self::normalisePhone($phone);
            if (! $phone) {
                return $result = DeliveryResult::make('meta', error: 'Enter a valid Pakistani mobile number.');
            }
            if (! self::configured()) {
                return $result = DeliveryResult::make('meta', error: 'Meta credentials are not configured.');
            }
            if ($error = self::validateMapping($mapping, self::templates())) {
                return $result = DeliveryResult::make('meta', error: $error);
            }
            $parameters = [];
            foreach ($mapping['parameters'] as $key) {
                $value = trim((string) ($variables[$key] ?? ''));
                if ($value === '') {
                    return $result = DeliveryResult::make('meta', error: 'Missing value for template variable '.$key.'.');
                }
                $parameters[] = ['type' => 'text', 'text' => $value];
            }
            $template = ['name' => $mapping['name'], 'language' => ['code' => $mapping['language']]];
            if ($parameters) {
                $template['components'] = [['type' => 'body', 'parameters' => $parameters]];
            }
            $response = self::request()->post(self::url(Settings::str('meta_phone_number_id').'/messages'), [
                'messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'template', 'template' => $template,
            ]);
            $messageId = $response->json('messages.0.id');
            $ok = $response->successful() && is_string($messageId) && $messageId !== '';
            $result = DeliveryResult::make('meta', $ok, $ok ? null : self::error($response), $ok ? DeliveryResult::safeText($messageId) : null,
                ['http_status' => $response->status(), 'code' => DeliveryResult::safeText($response->json('error.code'))]);
        } catch (\Throwable) {
            $result = DeliveryResult::make('meta', error: 'Meta request could not complete. Delivery may be unknown; check before resending.');
        } finally {
            if ($log) {
                DeliveryResult::log(WhatsAppLog::class, ['phone' => mb_substr(DeliveryResult::safeText($phone), 0, 50), 'message' => $mapping['name'] ?? '', 'template_id' => $id, 'order_id' => $orderId, 'customer_id' => $customerId], $result);
            }
        }

        return $result;
    }
}
