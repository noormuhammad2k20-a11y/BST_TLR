<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Money;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::query()
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (Notification $n) => $this->serialize($n));

        $stats = [
            'unread'   => $notifications->where('unread', true)->count(),
            'orders'   => Notification::where('category', 'orders')->count(),
            'payments' => Money::compact(
                (float) Payment::whereBetween('date', [now()->subDay()->startOfDay(), now()->endOfDay()])->sum('amount')
            ),
            'alerts'   => Order::overdue()->count(),
        ];

        return view('notifications.index', compact('notifications', 'stats'));
    }

    /**
     * Polled by the header bell and the Notification Center.
     */
    public function live(): JsonResponse
    {
        // Raise any due-date reminders the shop's alert rules now call for.
        // Throttled internally, so polling this endpoint cannot spam the bell.
        NotificationService::sweepDueOrders();

        return response()->json([
            'unread'        => NotificationService::unreadCount(),
            'preferences'   => NotificationService::clientPreferences(),
            'notifications' => Notification::latest()
                ->limit(20)
                ->get()
                ->map(fn (Notification $n) => $this->serialize($n)),
        ]);
    }

    public function markRead(Notification $notification): JsonResponse
    {
        $notification->markAsRead();
        NotificationService::flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'unread'  => NotificationService::unreadCount(),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $count = Notification::unread()->update(['is_read' => true, 'read_at' => now()]);
        NotificationService::flushCache();

        return response()->json([
            'success' => true,
            'message' => $count > 0
                ? "{$count} notification(s) marked as read."
                : 'You were already all caught up.',
            'unread'  => 0,
        ]);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        $notification->delete();
        NotificationService::flushCache();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted.',
            'unread'  => NotificationService::unreadCount(),
        ]);
    }

    /* ------------------------------------------------------------------ */

    private function serialize(Notification $n): array
    {
        return [
            'id'     => $n->id,
            'type'   => $n->category ?: 'system',
            'icon'   => $n->icon ?: 'fa-solid fa-circle-info',
            'color'  => $n->color ?: 'info',
            'title'  => $n->display_title,
            'desc'   => $n->message,
            'time'   => $n->created_at->diffForHumans(),
            'unread' => !$n->is_read,
            'action' => $n->action_url ?: route('dashboard'),
            'date'   => $n->date_bucket,
        ];
    }
}
