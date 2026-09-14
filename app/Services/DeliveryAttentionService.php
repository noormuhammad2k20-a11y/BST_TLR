<?php
namespace App\Services;

final class DeliveryAttentionService
{
    public function run(): array
    {
        $result=['started'=>app(OrderAutoProgress::class)->run(),'alerts'=>0,'daily'=>0];
        app(CollectionNotifications::class)->reconcile();
        if (!Settings::bool('delivery_alerts_enabled') || !Settings::bool('delivery_dashboard_alerts_enabled')) return $result;
        $now=now(Settings::timezone());
        if (!DeliveryTiming::isOpen($now)) return $result;
        $data=app(CollectionBoard::class)->data();
        $stats=$data['stats'];
        $alerts=[
            ['due-today',"Customers Due Today",$stats['dueToday'],'customers have orders due today.','Due Today'],
            ['collection-needed','Collection Notifications Needed',$stats['needsNotification'],'customers have ready garments and need their first collection SMS.','Notification Needed'],
            ['upcoming','Upcoming Due Dates',$stats['upcoming'],'customers have orders due within '.Settings::int('delivery_alert_before_days').' days.','Upcoming'],
            ['sms-failed','Collection SMS Failed',$stats['smsFailed'],'customers have failed or uncertain SMS attempts. Check before retrying.','SMS Failed'],
            ['waiting-long','Orders Waiting 7+ Days',$stats['waitingLong'],'orders have been ready for at least seven days.','Waiting 7+ Days'],
        ];
        if (Settings::bool('delivery_overdue_alerts_enabled')) $alerts[]=['overdue','Overdue / Waiting Customers',$stats['overdue'],'customers are past their promised collection time.','Overdue'];
        if (Settings::bool('collection_reminder_alerts_enabled')) $alerts[]=['reminder','Collection Reminders Due',$stats['reminderDue'],'customers are eligible for a collection reminder.','Reminder Due'];
        foreach ($alerts as [$key,$title,$count,$message,$filter]) {
            if (!$count) continue;
            $added=NotificationService::pushOnce('collection-alert:'.$key.':'.$now->toDateString(),$title,$count.' '.$message,actionUrl:route('delivery.index',['filter'=>$filter]));
            if ($added) $result['alerts']++;
            if ($added && $key==='due-today') $result['daily']++;
        }
        return $result;
    }
}

