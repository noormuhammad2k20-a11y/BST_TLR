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
            if ($event === 'order-ready') {
                $current = Order::find($order->id);
                if (!$current || $current->status !== 'Ready') {
                    return ['sent' => false, 'status' => 'skipped', 'error' => 'Verify the garments and mark the order Ready first.', 'channels' => []];
                }
                if (!Order::whereKey($order->id)->where('status', 'Ready')
                    ->whereNull('ready_sms_attempted_at')->whereNull('notified_at')
                    ->update(['ready_sms_attempted_at' => now()])) {
                    return ['sent' => $current->notified_at !== null, 'status' => 'duplicate',
                        'error' => $current->notified_at ? null : 'Pickup notice already attempted. Check the SMS log before sending a manual message.', 'channels' => []];
                }
                $order = $current->load('customer');
            }
            $sms = SmsService::sendTemplate($event, $order, $extra)
                ?? DeliveryResult::make('sms', error: 'The SMS template is disabled or unavailable.');
        } catch (\Throwable) {
            $sms = DeliveryResult::make('sms', error: 'SMS notification could not be completed.');
        }
        $sent = $sms['sent'];
        if ($event === 'order-ready') {
            if ($sent) {
                Order::whereKey($order->id)->update(['notified_at' => now()]);
            } else {
                // Never log provider credentials, customer phone numbers, or response bodies.
                \Illuminate\Support\Facades\Log::warning('Order pickup SMS was not accepted; inspect SMS logs before retrying.', ['order_id' => $order->id]);
            }
        }

        return ['sent' => $sent, 'status' => $sms['status'], 'channels' => ['sms' => $sms],
            'error' => $sms['error']];
    }
}
