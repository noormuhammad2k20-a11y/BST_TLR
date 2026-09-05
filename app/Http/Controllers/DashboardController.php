<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\StatsService;
use App\View\Composers\LayoutComposer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly OrderService $orders)
    {
    }

    public function index()
    {
        // Keep statuses honest without needing a scheduler running.
        $this->orders->flagOverdueOrders();

        $stats = StatsService::dashboard();

        $dueToday = Order::with('customer:id,name,phone')
            ->dueToday()
            ->orderBy('delivery_date')
            ->get();

        $recentOrders = Order::with('customer:id,name,phone')
            ->latest()
            ->take(5)
            ->get();

        $activityFeed = StatsService::activityFeed();
        $revenueTrend = StatsService::revenueTrend(30);

        $ordersJs = $recentOrders->map(fn (Order $o) => $this->serialize($o));

        return view('dashboard', compact(
            'stats',
            'dueToday',
            'recentOrders',
            'activityFeed',
            'revenueTrend',
            'ordersJs'
        ));
    }

    /**
     * Polled by the dashboard to refresh counters, the activity feed and the
     * due-today list without a full page reload.
     */
    public function live(Request $request): JsonResponse
    {
        $days = (int) $request->query('days', 30);
        $days = in_array($days, [7, 30, 365], true) ? $days : 30;

        return response()->json([
            'stats'     => StatsService::dashboard(),
            'activity'  => StatsService::activityFeed(),
            'due_today' => Order::with('customer:id,name')
                ->dueToday()
                ->orderBy('delivery_date')
                ->get()
                ->map(fn (Order $o) => [
                    'id'       => $o->id,
                    'number'   => $o->display_number,
                    'customer' => $o->customer?->name ?? 'Unknown',
                    'garment'  => $o->primary_item_name,
                ]),
            'revenue'   => StatsService::revenueTrend($days),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Tiny payload used by every page to keep the sidebar badges fresh.
     */
    public function counters(LayoutComposer $composer): JsonResponse
    {
        return response()->json(['counters' => $composer->counters()]);
    }

    private function serialize(Order $order): array
    {
        return [
            'id'       => $order->display_number,
            'db_id'    => $order->id,
            'customer' => $order->customer?->name ?? 'Unknown',
            'phone'    => $order->customer?->phone ?? '',
            'garment'  => $order->primary_item_name,
            'fabric'   => $order->fabric ?? '',
            'status'   => $order->status,
            'amount'   => (float) $order->total,
            'advance'  => (float) $order->advance,
            'due'      => $order->due_label,
            'notes'    => $order->notes ?? '',
        ];
    }
}
