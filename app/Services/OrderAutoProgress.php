<?php

namespace App\Services;

use App\Models\{Order, OrderStatusHistory};
use Illuminate\Support\Facades\DB;

/** Timed workshop estimates stop at verification; never confirm pickup or send SMS. */
final class OrderAutoProgress
{
    public function run(): int
    {
        config(['app.timezone' => Settings::timezone()]);
        date_default_timezone_set(Settings::timezone());
        $now = now();
        $changed = 0;
        Order::whereIn('status', ['Received', 'Pending', 'Stitching', 'In Progress'])
            ->whereNotNull('delivery_date')->chunkById(200, function ($orders) use ($now, &$changed) {
                foreach ($orders as $candidate) {
                    $changed += DB::transaction(function () use ($candidate, $now) {
                        $order = Order::whereKey($candidate->id)->lockForUpdate()->first();
                        if (!$order || !in_array($order->status, ['Received', 'Pending', 'Stitching', 'In Progress'], true) || !$order->delivery_date) return 0;
                        $start = $order->created_at;
                        $seconds = max(0, $start->diffInSeconds($order->delivery_date, false));
                        $rank = ['Received'=>0, 'Pending'=>1, 'In Progress'=>2, 'Stitching'=>2];
                        $currentRank = $rank[$order->status];
                        $count = 0;
                        foreach (['Pending'=>0.2, 'Stitching'=>0.5, 'Ready for Verification'=>1.0] as $status => $fraction) {
                            $stageRank = ['Pending'=>1, 'Stitching'=>2, 'Ready for Verification'=>3][$status];
                            $at = $start->copy()->addSeconds((int) ceil($seconds * $fraction));
                            if ($stageRank <= $currentRank || $now->lt($at)) continue;
                            $from = $order->status;
                            $order->forceFill(['status'=>$status, 'progress'=>Order::progressFor($status)])->save();
                            OrderStatusHistory::create(['order_id'=>$order->id, 'from_status'=>$from, 'to_status'=>$status,
                                'label'=>'Automatic scheduled stage: '.$status,
                                'note'=>'Based on booking and promised delivery time; physical verification is still required.',
                                'actor_name'=>'System', 'created_at'=>$at, 'updated_at'=>$now]);
                            $count++;
                        }
                        return $count;
                    });
                }
            });
        if ($changed) StatsService::flush();
        return $changed;
    }
}
