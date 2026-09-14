<?php

namespace App\Services;

use App\Models\{Customer, Order, SmsLog};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CollectionNotifications
{
    public const STATUSES = ['Ready for Verification','Ready'];
    public const SUCCESS = ['accepted','sent','delivered'];

    public function state(Order $order, ?\Illuminate\Support\Collection $phoneLogs = null): array
    {
        $now = now(Settings::timezone());
        $logs = $order->collectionMessages;
        $sent = $logs->whereIn('status',self::SUCCESS)->whereNotNull('sent_at')->sortBy('sent_at');
        $first = $sent->first()?->sent_at ?? $order->notified_at;
        $last = $sent->last()?->sent_at ?? $order->notified_at;
        $reminder = $sent->filter(fn ($log) => $log->pivot->reason === 'collection-reminder')->last()?->sent_at;
        $latest = $logs->last();
        $phone = NotificationPhone::normalize($order->customer?->phone) ?? $order->customer?->phone;
        $phoneLogs ??= SmsLog::whereNotNull('reason')->whereIn('phone',[$phone,'+'.$phone])->get();
        $phoneLast = $phoneLogs->whereIn('status',self::SUCCESS)->whereNotNull('sent_at')->sortBy('sent_at')->last()?->sent_at;
        $pending = $phoneLogs->whereIn('status',['sending','unknown'])->isNotEmpty();
        $dueAt = $last?->copy()->addDays(max(1,Settings::int('collection_reminder_days')));
        if ($dueAt && $phoneLast) $dueAt = $dueAt->max($phoneLast->copy()->addDays(max(1,Settings::int('collection_reminder_days'))));
        $reminderDue = Settings::bool('collection_reminder_enabled') && $order->status === 'Ready' && !$order->delivered_at && $dueAt && $now->gte($dueAt);
        $eligible = in_array($order->status,self::STATUSES,true) && !$order->delivered_at;
        $reason = $first ? 'collection-reminder' : 'collection-first';
        $blocked = match (true) {
            !$eligible => 'Garments are not awaiting collection.',
            !$order->customer || (bool)$order->customer->anonymized_at => 'Customer contact data is unavailable.',
            !filled($order->customer?->phone) => 'Missing customer phone.',
            !NotificationPhone::normalize($order->customer?->phone) => 'Enter a valid Pakistani mobile number on the customer profile.',
            $pending => 'An SMS is sending or its result is unknown. Check SMS History before retrying.',
            $first && !Settings::bool('collection_reminder_enabled') => 'Customer reminders are disabled in Settings.',
            (bool)($phoneLast && $phoneLast->gt($now->copy()->subMinutes(2))) => 'This customer was just notified. Wait 2 minutes before notifying another order.',
            $first && !$reminderDue => 'Next reminder: '.$dueAt?->format('d M Y, g:i A'),
            $first && !Settings::bool('collection_reminder_sms_enabled') => 'Reminder SMS is disabled in Settings.',
            default => null,
        };
        return ['firstSmsAt'=>$first?->toIso8601String(),'lastSmsAt'=>$last?->toIso8601String(),
            'lastReminderAt'=>$reminder?->toIso8601String(),'nextReminderAt'=>$eligible && Settings::bool('collection_reminder_enabled') ? $dueAt?->toIso8601String() : null,
            'reminderDue'=>(bool)$reminderDue,'needsNotification'=>$eligible && !$first,
            'smsCount'=>max($sent->count(),$first ? 1 : 0),'attemptCount'=>$logs->count(),'lastSmsStatus'=>$latest?->status ?? $order->ready_sms_state,
            'lastSmsError'=>$latest?->error,'daysWaiting'=>$first ? max(0,(int)$first->diffInDays($now)) : 0,
            'canNotify'=>$blocked === null,'blockedReason'=>$blocked,'smsReason'=>$reason];
    }

    public function sendOrders(array $ids): array
    {
        if (DB::transactionLevel() > 0) throw \Illuminate\Validation\ValidationException::withMessages(['sms'=>'Send collection SMS after the database transaction commits.']);
        $ids = array_values(array_unique(array_map('intval',$ids)));
        $result = ['success'=>true,'sent'=>0,'failed'=>[],'skipped'=>[],'results'=>[],'promoted'=>0];
        $orders = Order::with('customer')->whereIn('id',$ids)->get();
        foreach (array_diff($ids,$orders->modelKeys()) as $id) $result['skipped'][]=['order'=>(string)$id,'customer'=>'','reason'=>'Order no longer available.'];
        // One SMS per phone for all selected eligible orders, including legacy
        // customer duplicates. The pivot records each order's first/reminder type.
        foreach ($orders->groupBy(fn ($o) => NotificationPhone::normalize($o->customer?->phone) ?? 'invalid:'.$o->customer_id) as $group) {
            $claim = $this->claim($group->modelKeys());
            $result['skipped'] = array_merge($result['skipped'],$claim['skipped']);
            if (!$claim['log']) continue;
            $log = $claim['log'];
            $sms = $log->status === 'failed' ? DeliveryResult::make($log->provider,error:$log->error)
                : SmsService::send($log->phone,$log->message,$log->order_id,$log->customer_id,$log->template_id,false);
            // Persist the provider outcome BEFORE transitioning. A crash after
            // acceptance cannot cause a repeat send: reconciliation uses this log.
            $log->forceFill(['status'=>$sms['status'],'provider'=>$sms['provider'],'provider_message_id'=>$sms['message_id'],
                'error'=>$sms['error'],'api_response'=>json_encode($sms['metadata']),'sent_at'=>$sms['sent'] ? now() : null])->save();
            $entry = ['order'=>implode(', ',$claim['numbers']),'customer'=>$claim['customer']];
            if ($sms['sent']) {
                $result['promoted'] += $this->finalize($log);
                $result['sent']++;
                $result['results'][] = $entry;
            } else {
                Order::whereIn('id',$claim['ids'])->update(['ready_sms_state'=>$sms['status']]);
                $result['failed'][] = $entry + ['reason'=>$sms['error'] ?: 'SMS not accepted.'];
            }
        }
        StatsService::flush();
        $result['message'] = sprintf('%d SMS sent successfully. %d failed. %d orders skipped.',$result['sent'],count($result['failed']),count($result['skipped']));
        foreach ($result['skipped'] as $skip) $result['message'] .= ' '.$skip['order'].': '.$skip['reason'];
        return $result;
    }

    private function claim(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $orders = Order::with(['customer','collectionMessages'])->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            $claim = ['log'=>null,'skipped'=>[],'ids'=>[],'numbers'=>[],'customer'=>$orders->first()?->customer?->name ?? 'Customer'];
            if ($orders->isEmpty()) return $claim;
            $phone = NotificationPhone::normalize($orders->first()->customer?->phone) ?? (string)$orders->first()->customer?->phone;
            $hash = hash('sha256',$phone);
            DB::table('collection_sms_locks')->insertOrIgnore(['phone_hash'=>$hash]);
            DB::table('collection_sms_locks')->where('phone_hash',$hash)->lockForUpdate()->first();
            $phoneLogs = SmsLog::whereNotNull('reason')->whereIn('phone',[$phone,'+'.$phone])->get();
            $messages = []; $reasons = []; $templateErrors = [];
            foreach ($orders as $order) {
                if ((NotificationPhone::normalize($order->customer?->phone) ?? (string)$order->customer?->phone) !== $phone) {
                    $claim['skipped'][] = ['order'=>$order->display_number,'customer'=>$order->customer?->name ?? 'Customer','reason'=>'Customer phone changed during selection. Refresh and retry.'];
                    continue;
                }
                $state = $this->state($order,$phoneLogs);
                if (!$state['canNotify']) {
                    $claim['skipped'][] = ['order'=>$order->display_number,'customer'=>$order->customer?->name ?? 'Customer','reason'=>$state['blockedReason']];
                    continue;
                }
                $template = $state['smsReason'] === 'collection-first' ? 'order-ready' : 'collection-reminder';
                try {
                    $message = SmsService::renderTemplate($template,NotificationVariables::variablesForOrder($order));
                } catch (\InvalidArgumentException $error) {
                    $message = null;
                    $templateErrors[] = $error->getMessage();
                }
                $messages[] = $message ?? '';
                $reasons[$order->id] = $state['smsReason'];
                $claim['ids'][] = $order->id; $claim['numbers'][] = $order->display_number;
            }
            if (!$claim['ids']) return $claim;
            $reason = count(array_unique($reasons)) === 1 ? reset($reasons) : 'collection-mixed';
            $attempt = (string)Str::uuid();
            $log = SmsLog::create(['phone'=>$phone,'message'=>implode("\n\n",$messages),'order_id'=>$claim['ids'][0],
                'customer_id'=>$orders->first()->customer_id,'template_id'=>$reason === 'collection-first' ? 'order-ready' : 'collection-reminder',
                'reason'=>$reason,'attempt_key'=>$attempt,'status'=>'sending','provider'=>SmsService::provider()]);
            foreach ($reasons as $id=>$type) DB::table('collection_sms_orders')->insert(['sms_log_id'=>$log->id,'order_id'=>$id,'reason'=>$type]);
            // A disabled template is a logged failure, never an empty/partial send.
            if (in_array('',$messages,true)) {
                $log->update(['message'=>'','status'=>'failed','error'=>$templateErrors ? implode(' ', array_unique($templateErrors)) : 'A required collection SMS template is disabled or empty. Check SMS Settings.']);
                $claim['log']=$log;
                return $claim;
            }
            Order::whereIn('id',$claim['ids'])->update(['ready_sms_attempted_at'=>now(),'ready_sms_state'=>'sending','ready_sms_attempt_id'=>$attempt]);
            $claim['log']=$log;
            return $claim;
        });
    }

    public function finalize(SmsLog $log): int
    {
        if (!in_array($log->status,self::SUCCESS,true) || !$log->sent_at) return 0;
        return DB::transaction(function () use ($log) {
            $count=0;
            foreach ($log->collectionOrders()->orderBy('orders.id')->lockForUpdate()->get() as $order) {
                if ($order->trashed() || $order->delivered_at || !in_array($order->status,self::STATUSES,true)) continue;
                $order->forceFill(['notified_at'=>$order->notified_at ?? $log->sent_at,'ready_sms_state'=>'sent'])->save();
                if ($order->status === 'Ready for Verification') {
                    app(OrderService::class)->changeStatus($order,'Ready','Customer collection SMS accepted by provider',true);
                    $count++;
                }
            }
            return $count;
        });
    }

    public function reconcile(): void
    {
        // Only durable accepted messages can recover a status transition.
        SmsLog::whereIn('status',self::SUCCESS)->whereHas('collectionOrders',fn ($q)=>$q->where('status','Ready for Verification'))
            ->each(fn ($log)=>$this->finalize($log));
    }
}
