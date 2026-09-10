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
        try {
            $sms = SmsService::sendTemplate($event, $order, $extra)
                ?? DeliveryResult::make('sms', error: 'The SMS template is disabled or unavailable.');
        } catch (\Throwable) {
            $sms = DeliveryResult::make('sms', error: 'SMS notification could not be completed.');
        }
        $sent = $sms['sent'];

        return ['sent' => $sent, 'status' => $sms['status'], 'channels' => ['sms' => $sms],
            'error' => $sms['error']];
    }
}
