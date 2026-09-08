<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\DB;

final class CustomerNotificationDispatcher
{
    public static function dispatch(string $event, Order $order, array $extra = []): array
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(fn () => self::dispatch($event, $order, $extra));

            return ['sent' => false, 'status' => 'pending', 'error' => null, 'channels' => []];
        }
        $channels = [];
        foreach (['whatsapp' => WhatsAppService::class, 'sms' => SmsService::class] as $name => $service) {
            try {
                $channels[$name] = $service::sendTemplate($event, $order, $extra);
            } catch (\Throwable) {
                $channels[$name] = DeliveryResult::make($name, error: 'Notification could not be completed.');
            }
        }
        $sent = ($channels['whatsapp']['sent'] ?? false) || ($channels['sms']['sent'] ?? false);

        return ['sent' => $sent, 'status' => $sent ? 'accepted' : 'failed', 'channels' => $channels,
            'error' => $sent ? null : implode(' ', array_filter(array_column(array_filter($channels), 'error')))];
    }
}
