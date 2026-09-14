<?php
namespace App\Services;

use App\Models\{Order,SmsLog};

final class CollectionBoard
{
    public function data(): array
    {
        $now=now(Settings::timezone());
        $notifier=app(CollectionNotifications::class);
        $orders=Order::whereIn('status',array_merge(Order::OPEN_STATUSES,['Delivered','Completed']))->with(['customer','collectionMessages','statusHistories','lineItems.productService','lineItems.pieces'])
            ->orderBy('delivery_date')->orderBy('id')->get();
        $phoneLogs=SmsLog::whereNotNull('reason')->get()->groupBy(fn ($l)=>NotificationPhone::normalize($l->phone) ?? $l->phone);
        $rows=$orders->map(function ($order) use ($now,$notifier,$phoneLogs) {
            $phone=NotificationPhone::normalize($order->customer?->phone) ?? $order->customer?->phone;
            $state=$notifier->state($order,$phoneLogs->get($phone,collect()));
            $due=$order->delivery_date?->copy()->timezone(Settings::timezone());
            $days=$due ? (int)$now->copy()->startOfDay()->diffInDays($due->copy()->startOfDay(),false) : null;
            $ready=in_array($order->status,CollectionNotifications::STATUSES,true);
            $collected=in_array($order->status,['Delivered','Completed'],true) || (bool)$order->delivered_at;
            $verifiedAt=$order->statusHistories->where('to_status','Ready for Verification')->first()?->created_at ?? $order->completed_at;
            $readyAt=$order->statusHistories->where('to_status','Ready')->first()?->created_at ?? $order->notified_at;
            return $state + app(CustomerLedger::class)->orderDues($order) + ['db_id'=>$order->id,'order_id'=>$order->id,'customer_id'=>$order->customer_id,
                'id'=>$order->display_number,'cust'=>$order->customer?->name ?? 'Customer removed','phone'=>$order->customer?->phone ?? '',
                'gmt'=>$order->primary_item_name,'pieces'=>$order->quantity,'status'=>$collected ? 'Delivered' : $order->status,'orderStatus'=>$order->status,
                'collectionReady'=>$ready && !$collected,'overdue'=>!$collected && $days !== null && $days < 0,'dueToday'=>!$collected && $days === 0,'dueTomorrow'=>!$collected && $days === 1,
                'upcoming'=>!$collected && $days !== null && $days > 0 && $days <= Settings::int('delivery_alert_before_days'),
                'dueDays'=>$days,'dueDate'=>$due?->toIso8601String(),
                'due'=>$collected ? 'Collected' : ($due ? match(true) {$days === 0=>'Due today', $days === 1=>'Due tomorrow', $days < 0=>abs($days).' days overdue', default=>'Due in '.$days.' days'} : 'No due date'),
                'verificationAt'=>$verifiedAt?->toIso8601String(),'readyAt'=>$readyAt?->toIso8601String(),'readyDays'=>$readyAt ? max(0,(int)$readyAt->diffInDays($now)) : 0,
                'createdAt'=>$order->created_at?->toIso8601String(),'deliveredAt'=>$order->delivered_at?->toIso8601String(),
                'amount'=>(float)$order->total,'advance'=>(float)$order->advance,'balance'=>$order->balance_due,'paymentStatus'=>$order->payment_status,
                'notified'=>$state['firstSmsAt'] !== null,'notes'=>'',
                'smsFailed'=>in_array($state['lastSmsStatus'],['failed','unknown'],true)];
        });
        $customers=fn ($filtered)=>$filtered->pluck('customer_id')->unique()->count();
        $collection=$rows->where('collectionReady',true);
        $stats=['total'=>$customers($collection),'ready'=>$customers($collection->where('status','Ready')),
            'dueToday'=>$customers($rows->where('dueToday',true)),'needsNotification'=>$customers($collection->where('needsNotification',true)),
            'reminderDue'=>$customers($collection->where('reminderDue',true)),
            'overdue'=>$customers($rows->where('overdue',true)),'waiting'=>$customers($collection->where('status','Ready')),
            'waitingLong'=>$collection->where('status','Ready')->where('readyDays','>=',7)->count(),
            'upcoming'=>$customers($rows->where('upcoming',true)),
            'smsSentToday'=>SmsLog::whereNotNull('reason')->whereIn('status',CollectionNotifications::SUCCESS)->whereBetween('sent_at',[$now->copy()->startOfDay(),$now->copy()->endOfDay()])->count(),
            'smsFailed'=>$customers($collection->where('smsFailed',true))];
        return ['deliveries'=>$rows,'stats'=>$stats,'settings'=>[
            'alertsEnabled'=>Settings::bool('delivery_alerts_enabled'),'overdueEnabled'=>Settings::bool('delivery_overdue_alerts_enabled'),
            'reminderAlertsEnabled'=>Settings::bool('collection_reminder_alerts_enabled'),'reminderDays'=>Settings::int('collection_reminder_days'),
            'beforeDays'=>Settings::int('delivery_alert_before_days'),'dashboardEnabled'=>Settings::bool('delivery_dashboard_alerts_enabled'),
            'smsEnabled'=>SmsService::enabled(),'smsConfigured'=>SmsService::configured(), 'timezone'=>Settings::timezone(),
        ]];
    }
}
